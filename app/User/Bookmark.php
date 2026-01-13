<?php

namespace Gazelle\User;

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

class Bookmark extends \Gazelle\BaseUser {
    final public const tableName = 'pm_conversations_users'; // not really
    final protected const CACHE_KEY = 'bk_%s_%d';

    protected array $bookmarkList;

    public function flush(): static {
        unset($this->bookmarkList);
        $this->user()->flush();
        return $this;
    }

    /**
     * Get the bookmark schema.
     * Recommended usage:
     * [$table, $column] = $bookmark->schema('torrent');
     */
    public function schema(Bookmarked $object): array {
        return match ($object->bookmarkTable()) {
            'bookmark_artist'  => ['bookmarks_artists',  'ArtistID'],
            'bookmark_collage' => ['bookmarks_collages', 'CollageID'],
            'bookmark_request' => ['bookmarks_requests', 'RequestID'],
            'bookmark_tgroup'  => ['bookmarks_torrents', 'GroupID'],
            default   => [null, null],
        };
    }

    /**
     * Bookmark an object by a user
     */
    public function create(Bookmarked $object): bool {
        [$table, $column] = $this->schema($object);
        if (
            (bool)self::$db->scalar("
                SELECT 1 FROM $table WHERE UserID = ? AND $column = ?
                ", $this->id, $object->id()
            )
        ) {
            // overbooked
            return false;
        }
        $type = explode('_', $object->bookmarkTable())[1];
        if ($type === 'tgroup') {
            self::$db->prepared_query("
                INSERT IGNORE INTO bookmarks_torrents
                       (GroupID,  UserID, Sort)
                VALUES (?,        ?,
                    (1 + coalesce((SELECT max(m.Sort) from bookmarks_torrents m WHERE m.UserID = ?), 0))
                )", $object->id(), $this->id, $this->id
            );
            $this->pg()->prepared_query("
                insert into bookmark_tgroup
                       (id_tgroup, id_user, seq)
                values (?, ?,
                    (1 + coalesce((select max(m.seq) from bookmark_tgroup m where m.id_user = ?), 0))
                )
                ", $object->id(), $this->id, $this->id
            );
            self::$cache->delete_multi([
                "bookmarks_group_ids_" . $this->id
            ]);
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
            $this->pg()->prepared_query("
                insert into {$object->bookmarkTable()}
                       ({$object->bookmarkColumnName()}, id_user)
                values (?, ?)
                ", $object->id(), $this->id
            );
        }

        self::$cache->delete_value(sprintf(self::CACHE_KEY, $type, $this->id));
        $this->flush();
        return true;
    }

    /**
     * To see if an object is bookmarked, fetch all bookmarks of that type.
     * This may seem like an inefficient way to go about this, but it means
     * that the database is only hit once, no matter how many checks are
     * made (and most pages where this is needed may have dozens)...
     */
    public function isBookmarked(Bookmarked $object): bool {
        $type = explode('_', $object->bookmarkTable())[1];
        if (isset($this->bookmarkList[$type])) {
            // must use the method name for a BaseObject declared via an interface
            return in_array($object->id(), $this->bookmarkList[$type]);
        }
        $key = sprintf(self::CACHE_KEY, $type, $this->id);
        $all = self::$cache->get_value($key);
        if ($all === false) {
            $q = self::$db->get_query_id();
            $bookmarkTable = match ($type) {
                'artist'  => 'bookmarks_artists',
                'collage' => 'bookmarks_collages',
                'request' => 'bookmarks_requests',
                'tgroup'  => 'bookmarks_torrents',
                default   => 'bookmark_table_unknown',
            };
            $columnName = match ($type) {
                'artist'  => 'ArtistID',
                'collage' => 'CollageID',
                'request' => 'RequestID',
                'tgroup'  => 'GroupID',
                default   => 'bookmark_column_unknown',
            };
            self::$db->prepared_query("
                SELECT $columnName AS c
                FROM $bookmarkTable
                WHERE UserID = ?
                ", $this->id
            );
            $all = self::$db->collect('c');
            self::$db->set_query_id($q);
            self::$cache->cache_value($key, $all, 0);
        }
        $this->bookmarkList[$type] = $all;
        return in_array($object->id(), $this->bookmarkList[$type]);
    }

    /**
     * Returns an array with User Bookmark data: group IDs, collage data, torrent data
     * @return array Group IDs, Bookmark Data, Torrent List
     */
    public function tgroupBookmarkList(): array {
        $key = "bookmarks_group_ids_" . $this->id;
        $bookmarkList = self::$cache->get_value($key);
        $bookmarkList = false;
        self::$db->prepared_query("
                SELECT b.GroupID AS tgroup_id,
                    b.Sort       AS sequence,
                    b.Time       AS created
                FROM bookmarks_torrents b
                WHERE b.UserID = ?
                ORDER BY b.Sort, b.Time
                ", $this->id
        );
        $bookmarkList = self::$db->to_array(false, MYSQLI_ASSOC);
        self::$cache->cache_value($key, $bookmarkList, 3600);
        return $bookmarkList;
    }

    public function tgroupArtistLeaderboard(
        \Gazelle\Manager\Artist $artistMan = new \Gazelle\Manager\Artist(),
    ): array {
        self::$db->prepared_query("
            SELECT aa.ArtistID AS id,
                count(*) AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_artists ta USING (GroupID)
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE b.userid = ?
            GROUP BY aa.ArtistID
            ORDER BY total DESC, id
            LIMIT 10
            ", $this->id
        );
        $result = self::$db->to_array(false, MYSQLI_ASSOC);
        $list = [];
        foreach ($result as $item) {
            $artist = $artistMan->findById($item['id']);
            if ($artist) {
                $item['name'] = $artist->name();
                $list[] = $item;
            }
        }
        return $list;
    }

    public function tgroupArtistTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_artists ta USING (GroupID)
            WHERE b.UserID = ?
            ", $this->id
        );
    }

    public function tgroupTagLeaderboard(): array {
        self::$db->prepared_query("
            SELECT t.Name AS name,
                count(*)  AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_tags ta USING (GroupID)
            INNER JOIN tags t ON (t.ID = ta.TagID)
            WHERE b.UserID = ?
            GROUP BY t.Name
            ORDER By 2 desc, t.Name
            LIMIT 10
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function torrentTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM bookmarks_torrents b
            INNER JOIN torrents t USING (GroupID)
            WHERE b.UserID = ?
            ", $this->id
        );
    }

    public function tgroupTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(DISTINCT b.GroupID)
            FROM bookmarks_torrents b
            INNER JOIN torrents t USING (GroupID)
            WHERE b.UserID = ?
            ", $this->id
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
        self::$db->prepared_query("
            SELECT b.GroupID       AS tgroup_id,
                b.Sort             AS seq,
                b.Time             AS added,
                group_concat(t.ID ORDER BY
                    t.GroupID, t.Remastered, (t.RemasterYear != 0) DESC, t.RemasterYear, t.RemasterTitle,
                    t.RemasterRecordLabel, t.RemasterCatalogueNumber, t.Media, t.Format, t.Encoding, t.ID
                ) AS torrent_list
            FROM bookmarks_torrents b
            INNER JOIN torrents t USING (GroupID)
            WHERE b.UserID = ?
            GROUP BY b.GroupID, b.Sort, b.Time
            ORDER BY seq, added
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
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
        $this->pg()->prepared_query('
            with snatched(id_tgroup, id_user) as (
                select distinct t."GroupID",
                    xs.uid
                from relay.torrents t
                inner join relay.xbt_snatched xs on (xs.fid = t."ID")
                where xs.uid = ?
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
        $this->pg()->prepared_query("
            delete from {$object->bookmarkTable()}
            where {$object->bookmarkColumnName()} = ?
                and id_user = ?
            ", $object->id(), $this->id
        );
        $type = explode('_', $object->bookmarkTable())[1];
        if ($type === 'tgroup' && self::$db->affected_rows()) {
            self::$cache->delete_value("bookmarks_group_ids_" . $this->id);
            new \Gazelle\TGroup($object->id())->stats()->increment('bookmark_total', -1);
        }
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $type, $this->id));
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
            $this->pg()->prepared_query("
                delete from $table where id_user = ?
                ", $this->id
            );
        }
        $this->flush();
        return $affected;
    }
}
