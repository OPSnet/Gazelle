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
            sprintf(self::CACHE_KEY, 'artist',  $this->id),
            sprintf(self::CACHE_KEY, 'collage', $this->id),
            sprintf(self::CACHE_KEY, 'request', $this->id),
            sprintf(self::CACHE_KEY, 'tgroup',  $this->id),
        ]);
        unset($this->bookmarkList);
        return $this;
    }

    /**
     * Get the bookmark schema.
     * Recommended usage:
     * [$table, $column] = $bookmark->schema('torrent');
     */
    public function schema(Bookmarked $object): array {
        return match ($object->bookmarkType()) {
            BookmarkType::artist  => ['bookmarks_artists',  'ArtistID'],
            BookmarkType::collage => ['bookmarks_collages', 'CollageID'],
            BookmarkType::request => ['bookmarks_requests', 'RequestID'],
            BookmarkType::tgroup  => ['bookmarks_torrents', 'GroupID'],
        };
    }

    /**
     * Bookmark an object by a user
     */
    public function create(Bookmarked $object): bool {
        [$table, $column] = $this->schema($object);
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
        if ($object->bookmarkType() === BookmarkType::tgroup) {
            self::$db->prepared_query("
                INSERT IGNORE INTO bookmarks_torrents
                       (GroupID,  UserID, Sort)
                VALUES (?,        ?,
                    (1 + coalesce((SELECT max(m.Sort) from bookmarks_torrents m WHERE m.UserID = ?), 0))
                )", $object->id(), $this->id, $this->id
            );
            $this->pg()->query("
                insert into bookmark_tgroup
                       (id_tgroup, id_user, seq)
                values ($1, $2,
                    (1 + coalesce((select max(m.seq) from bookmark_tgroup m where m.id_user = $2), 0))
                )
                ", $object->id(), $this->id
            );
            // Intf\Bookmarked has no knowledge of TGroup methods
            $tgroup = new TGroup($object->id());
            $tgroup->stats()->increment('bookmark_total');

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
        } else {
            self::$db->prepared_query("
                INSERT IGNORE INTO $table ($column, UserID) VALUES (?, ?)
                ", $object->id(), $this->id
            );
            $this->pg()->query("
                insert into bookmark_{$object->bookmarkType()->name}
                       (id_{$object->bookmarkType()->name}, id_user)
                values ($1, $2)
                ", $object->id(), $this->id
            );
        }
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $object->bookmarkType()->name, $this->id));
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
        self::$db->prepared_query("
            SELECT ag.ArtistID AS artist_id,
                aa.Name        AS artist_name,
                ba.Time        AS created
            FROM bookmarks_artists ba
            INNER JOIN artists_group ag USING (ArtistID)
            INNER JOIN artists_alias aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE ba.UserID = ?
            ORDER BY aa.Name
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
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
        self::$db->prepared_query("
            DELETE b
            FROM bookmarks_torrents AS b
            INNER JOIN (
                SELECT DISTINCT t.GroupID
                FROM torrents AS t
                INNER JOIN xbt_snatched AS s ON (s.fid = t.ID)
                WHERE s.uid = ?
            ) AS s USING (GroupID)
            WHERE b.UserID = ?
            ", $this->id, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->pg()->query('
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
            ', $this->id
        );
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, 'tgroup', $this->id),
            "bookmarks_group_ids_" . $this->id,
        ]);
        $this->flush();
        return $affected;
    }

    /**
     * Remove a bookmark of an object by a user
     */
    public function removeObject(Bookmarked $object): int {
        [$table, $column] = $this->schema($object);
        self::$db->prepared_query("
            DELETE FROM $table WHERE UserID = ? AND $column = ?
            ", $this->id, $object->id()
        );
        $affected = self::$db->affected_rows();
        $this->pg()->query("
            delete from bookmark_{$object->bookmarkType()->name}
            where id_{$object->bookmarkType()->name} = $1
                and id_user = $2
            ", $object->id(), $this->id
        );
        if ($object->bookmarkType() === BookmarkType::tgroup && self::$db->affected_rows()) {
            new TGroup($object->id())->stats()->increment('bookmark_total', -1);
        }
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $object->bookmarkType()->name, $this->id));
        $this->flush();
        return $affected;
    }

    public function remove(): int {
        $affected = 0;
        foreach (
            [
                'bookmarks_artists',
                'bookmarks_collages',
                'bookmarks_requests',
                'bookmarks_torrents',
            ] as $table
        ) {
            self::$db->prepared_query("
                DELETE FROM $table WHERE UserID = ?
                ", $this->id
            );
            $affected += self::$db->affected_rows();
        }
        foreach (
            [
                'bookmark_artist',
                'bookmark_collage',
                'bookmark_request',
                'bookmark_tgroup',
            ] as $table
        ) {
            $this->pg()->query("
                delete from $table where id_user = $1
                ", $this->id
            );
        }
        $this->flush();
        return $affected;
    }
}
