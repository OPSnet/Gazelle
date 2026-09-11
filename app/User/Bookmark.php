<?php

namespace Gazelle\User;

use Gazelle\Enum\BookmarkType;
use Gazelle\Intf\Bookmarked;
use Gazelle\BookmarkList;
use Gazelle\Artist;
use Gazelle\Feed;
use Gazelle\Collage;
use Gazelle\Request;
use Gazelle\TGroup;

/* Note: BaseObject defines a public readonly $id but when a sub-classed
 * object is typed through an interface, the property is not accessible.
 * For this reason, the id() method must be used.
 */

/* mysql_fdw cannot figure out how to push down the list of ids when a
 * group by needs to be performed on the Mysql side e.g.:
 *
 * from bookmark_tgroup b
 * inner join relay.torrents t on (t."GroupID" = b.id_tgroup)
 * where b.id_user = $1
 * group by t."GroupID"
 *
 * (A full table scan will be issued on torrents). Therefore it has to be
 * done in two steps. First a list of ids is generated in Postgres, and
 * those are passed to a Mysql query:
 *
 * WHERE t.GroupID IN ($placeholder)
 */

class Bookmark extends \Gazelle\BaseUser {
    final public const tableName = 'pm_conversations_users'; // not really
    final public const CACHE_KEY = 'bk_%s_%d'; // type id_user

    protected array $bookmarkList;

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, BookmarkType::artist->name,  $this->id),
            sprintf(self::CACHE_KEY, BookmarkType::collage->name, $this->id),
            sprintf(self::CACHE_KEY, BookmarkType::request->name, $this->id),
            sprintf(self::CACHE_KEY, BookmarkType::tgroup->name,  $this->id),
        ]);
        unset($this->bookmarkList);
        return $this;
    }

    /**
     * Bookmark an object by a user
     */
    public function create(Bookmarked $object): bool {
        if (
            (bool)$this->pg()->scalarex("
                select 1
                from bookmark_{$object->bookmarkType()->name}
                where id_user = $1
                    and id_{$object->bookmarkType()->name} = $2
                ", $this->id, $object->id()
            )
        ) {
            // overbooked
            return false;
        }
        if ($object->bookmarkType() !== BookmarkType::tgroup) {
            $this->pg()->query("
                insert into bookmark_{$object->bookmarkType()->name}
                       (id_{$object->bookmarkType()->name}, id_user)
                values ($1, $2)
                ", $object->id(), $this->id
            );
        } else {
            $this->pg()->query("
                insert into bookmark_tgroup
                       (id_tgroup, id_user, seq)
                values ($1, $2,
                    (1 + coalesce((select max(m.seq) from bookmark_tgroup m where m.id_user = $2), 0))
                )
                ", $object->id(), $this->id
            );
            // Intf\Bookmarked has no knowledge of TGroup properties, hence id()
            $tgroup = new TGroup($object->id());

            // RSS feed stuff
            $feed   = new Feed();
            $torMan = new \Gazelle\Manager\Torrent();
            foreach ($tgroup->torrentIdList() as $torrentId) {
                $torrent = $torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }
                $feed->populate('torrents_bookmarks_t_' . $this->user->announceKey(),
                    $feed->item(
                        "{$torrent->name()} [{$torrent->label($this->user)}]",
                        \Text::strip_bbcode($tgroup->description()),
                        "torrents.php?action=download&id={$torrentId}&torrent_pass=[[PASSKEY]]",
                        date('r'),
                        $this->user->username(),
                        $tgroup->location(),
                        implode(',', $tgroup->tagNameList()),
                    )
                );
            }
        }
        self::$cache->delete_value(
            sprintf(self::CACHE_KEY, $object->bookmarkType()->name, $this->id)
        );
        $this->flush();
        return true;
    }

    public function bookmarkList(BookmarkType $type): array {
        if (!isset($this->bookmarkList[$type->name])) {
            $key  = sprintf(self::CACHE_KEY, $type->name, $this->id);
            $list = self::$cache->get_value($key);
            if ($list === false) {
                if ($type === BookmarkType::tgroup) {
                    $list = $this->pg()->allByKeyex("{$type->name}_id", '
                        select b.id_tgroup as tgroup_id,
                            b.seq          as sequence,
                            b.created
                        from bookmark_tgroup b
                        where b.id_user = $1
                        order by b.seq, b.created
                        ', $this->id
                    );
                } else {
                    $list = $this->pg()->allByKeyex("{$type->name}_id", "
                        select b.id_{$type->name} as {$type->name}_id,
                            b.created
                        from bookmark_{$type->name} b
                        where b.id_user = $1
                        order by b.created
                        ", $this->id
                    );
                }
                self::$cache->cache_value($key, $list, 0);
            }
            $this->bookmarkList[$type->name] = $list;
        }
        return $this->bookmarkList[$type->name];
    }

    /**
     * To see if an object is bookmarked, fetch all bookmarks of that type.
     * This may seem like an inefficient way to go about this, but it means
     * that the database is only hit once, no matter how many checks are
     * made (and most pages where this is needed may have dozens)...
     */
    public function isBookmarked(Bookmarked $object): bool {
        return array_key_exists(
            $object->id(),
            $this->bookmarkList($object->bookmarkType()),
        );
    }

    public function tgroupArtistLeaderboard(
        \Gazelle\Manager\Artist $artistMan = new \Gazelle\Manager\Artist(),
    ): array {
        $idList = $this->pg()->columnex("
            select id_tgroup from bookmark_tgroup where id_user = $1
            ", $this->id
        );
        if ($idList === []) {
            return [];
        }
        $placeholder = placeholders($idList);
        self::$db->prepared_query("
            SELECT aa.ArtistID AS id,
                count(*)       AS total
            FROM torrents_artists    ta
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE ta.GroupID IN ($placeholder)
            GROUP BY aa.ArtistID
            ORDER BY total DESC, id
            LIMIT 10
            ", ...$idList
        );
        $list = [];
        foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $item) {
            $artist = $artistMan->findById($item['id']);
            if ($artist) {
                $item['name'] = $artist->name();
                $list[] = $item;
            }
        }
        return $list;
    }

    public function tgroupArtistTotal(): int {
        $idList = $this->pg()->columnex("
            select id_tgroup from bookmark_tgroup where id_user = $1
            ", $this->id
        );
        if ($idList === []) {
            return 0;
        }
        $placeholder = placeholders($idList);
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM torrents_artists ta
            WHERE ta.GroupID in ($placeholder)
            ", ...$idList
        );
    }

    public function tgroupTagLeaderboard(): array {
        return $this->pg()->allex('
            SELECT t.name,
                count(*) as total
            from bookmark_tgroup  b
            inner join tgroup_tag ta using (id_tgroup)
            inner join tag        t  using (id_tag)
            where b.id_user = $1
            group by t.name
            order bY total desc, t.name
            limit 10
            ', $this->id
        );
    }

    public function tgroupTotal(): int {
        return (int)$this->pg()->scalarex('
            select count(*) from bookmark_tgroup b where b.id_user = $1
            ', $this->id
        );
    }

    public function artistList(): array {
        return $this->pg()->allex(<<<END_SQL
            select ag."ArtistID" as artist_id,
                aa."Name"        as artist_name,
                ba.created
            from bookmark_artist           ba
            inner join relay.artists_group ag on (ag."ArtistID" = ba.id_artist)
            inner join relay.artists_alias aa on (aa."AliasID" = ag."PrimaryAlias")
            where ba.id_user = $1
            order by aa."Name"
            END_SQL, $this->id
        );
    }

    /**
     * Returns an array of torrent bookmarks
     * @return array containing [group_id, seq, added, torrent_id]
     */
    public function tgroupList(int $limit, int $offset): array {
        $args = $this->pg()->columnex("
            select id_tgroup from bookmark_tgroup where id_user = $1
            ", $this->id
        );
        if ($args === []) {
            return [];
        }
        $placeholder = placeholders_pg($args);
        $userPh   = $this->pg()->placeholder(count($args));
        $args[]   = $this->id;
        $limitPh  = "limit {$this->pg()->placeholder(count($args))}";
        $args[]   = $limit;
        $offsetPh = "offset {$this->pg()->placeholder(count($args))}";
        $args[]   = $offset;

        return $this->pg()->allex(<<<END_SQL
            with t(id_tgroup, torrent_list) as (
                select t."GroupID",
                array_agg(t."ID" order by
                    t."GroupID", (t."RemasterYear" != 0) DESC, t."RemasterYear", t."RemasterTitle",
                    t."RemasterRecordLabel", t."RemasterCatalogueNumber", t."Media", t."Format", t."Encoding", t."ID"
                )
                from relay.torrents t
                where t."GroupID" in ($placeholder)
                group by t."GroupID"
            )
            select b.id_tgroup     AS tgroup_id,
                b.seq              AS seq,
                b.created          AS added,
                t.torrent_list
            from bookmark_tgroup b
            inner join t using (id_tgroup)
            where b.id_user = $userPh
            order by b.seq, b.created
            $limitPh $offsetPh
            END_SQL,
                ...$args
        );
    }

    public function removeSnatched(): int {
        $affected = $this->pg()->query('
            with snatched(id_tgroup, id_user) as (
                select distinct t."GroupID",
                    xs.uid
                from relay.torrents t
                inner join relay.xbt_snatched xs on (xs.fid = t."ID")
                where xs.uid = $1
            )
            delete from bookmark_tgroup d
            using snatched
            where d.id_tgroup = snatched.id_tgroup
                and d.id_user = snatched.id_user
                and d.id_user = $1
            ', $this->id
        );
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, BookmarkType::tgroup->name, $this->id),
        ]);
        unset($this->bookmarkList);
        return $affected;
    }

    /**
     * Remove a bookmark of an object by a user
     */
    public function removeObject(Bookmarked $object): int {
        $affected = $this->pg()->query("
            delete from bookmark_{$object->bookmarkType()->name}
            where id_{$object->bookmarkType()->name} = $1
                and id_user = $2
            ", $object->id(), $this->id
        );
        if ($affected && $object->bookmarkType() === BookmarkType::tgroup) {
            new TGroup($object->id())->stats()->increment('bookmark_total', -1);
        }
        self::$cache->delete_value(
            sprintf(self::CACHE_KEY, BookmarkType::tgroup->name, $this->id)
        );
        unset($this->bookmarkList);
        return $affected;
    }

    /**
     * Uses (checkboxes) $_POST['remove'] to delete entries.
     * @param $list array<int, int>
     */
    public function removeList(array $list): int {
        $affected = $this->pg()->query("
            delete from bookmark_tgroup
            where id_user = $1
                and id_tgroup = any($2)
            ", $this->id,
            $this->pg()->integerConverter()->output($list)
        );
        self::$cache->delete_value(
            sprintf(self::CACHE_KEY, BookmarkType::tgroup->name, $this->id)
        );
        unset($this->bookmarkList);
        return $affected;
    }

    /**
     * Uses $_POST['sort'] values to update entries.
     * @param $list array<int, int>
     */
    public function modifyList(array $list): int {
        if ($list === []) {
            return 0;
        }
        $args         = [$this->id];
        $placeholders = [];
        foreach ($list as $tgroupId => $sequence) {
            $placeholders[] = sprintf(
                '($1,$%d,$%d)',
                count($args) + 1, count($args) + 2,
            );
            $args = array_merge($args,  [(int)$tgroupId, (int)$sequence]);
        }
        $affected = $this->pg()->query("
            insert into bookmark_tgroup
                (id_user, id_tgroup, seq)
            values " . implode(', ', $placeholders) . "
            on conflict (id_tgroup, id_user)
                do update set
                    seq = EXCLUDED.seq
            ", ...$args
        );
        self::$cache->delete_value(
            sprintf(self::CACHE_KEY, BookmarkType::tgroup->name, $this->id)
        );
        unset($this->bookmarkList);
        return $affected;
    }

    public function remove(): int {
        $affected = 0;
        foreach (
            [
                'bookmark_artist',
                'bookmark_collage',
                'bookmark_request',
                'bookmark_tgroup',
            ] as $table
        ) {
            $affected += $this->pg()->query("
                delete from $table where id_user = $1
                ", $this->id
            );
        }
        $this->flush();
        return $affected;
    }
}
