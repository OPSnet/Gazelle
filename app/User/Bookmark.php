<?php

namespace Gazelle\User;

class Bookmark extends \Gazelle\BaseUser {
    protected array $all;

    /**
     * Get the bookmark schema.
     * Recommended usage:
     * [$table, $column] = $bookmark->schema('torrent');
     *
     * @param string $type the type to get the schema for
     */
    public function schema($type): array {
        return match($type) {
            'artist'  => ['bookmarks_artists',  'ArtistID'],
            'collage' => ['bookmarks_collages', 'CollageID'],
            'request' => ['bookmarks_requests', 'RequestID'],
            'torrent' => ['bookmarks_torrents', 'GroupID'],
            default   => [null, null],
        };
    }

    /**
     * Fetch all bookmarks of a certain type for a user.
     * This may seem like an inefficient way to go about this, but it means
     * that the database is only hit once, no matter how * many checks are
     * made (and most pages where this is needed may have dozens)...
     *
     * @param string $type type of bookmarks to fetch
     * @return array the bookmarks
     */
    public function allBookmarks(string $type): array {
        if (isset($this->all)) {
            return $this->all;
        }
        $key = "bookmarks_{$type}_" . $this->user->id();
        $all = self::$cache->get_value($key);
        if ($all === false) {
            [$table, $column] = $this->schema($type);
            $q = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT $column
                FROM $table
                WHERE UserID = ?
                ", $this->user->id()
            );
            $all = self::$db->collect($column);
            self::$db->set_query_id($q);
            self::$cache->cache_value($key, $all, 0);
        }
        $this->all = $all;
        return $this->all;
    }

    /**
     * Check if an artist is bookmarked by a user
     */
    public function isArtistBookmarked(int $artistId): bool {
        return in_array($artistId, $this->allBookmarks('artist'));
    }

    /**
     * Check if a collage is bookmarked by a user
     */
    public function isCollageBookmarked(int $collageId): bool {
        return in_array($collageId, $this->allBookmarks('collage'));
    }

    /**
     * Check if a request is bookmarked by a user
     */
    public function isRequestBookmarked(int $requestId): bool {
        return in_array($requestId, $this->allBookmarks('request'));
    }

    /**
     * Check if an torrent is bookmarked by a user
     */
    public function isTorrentBookmarked(int $tgroupId): bool {
        return in_array($tgroupId, $this->allBookmarks('torrent'));
    }

    /**
     * Returns an array with User Bookmark data: group IDs, collage data, torrent data
     * @return array Group IDs, Bookmark Data, Torrent List
     */
    public function tgroupBookmarkList(): array {
        $key = "bookmarks_group_ids_" . $this->user->id();
        $bookmarkList = self::$cache->get_value($key);
        $bookmarkList = false;
        if ($bookmarkList === false) {
            self::$db->prepared_query("
                SELECT GroupID AS tgroup_id,
                    Sort       AS sequence,
                    `Time`     AS created
                FROM bookmarks_torrents
                WHERE UserID = ?
                ORDER BY Sort, `Time`
                ", $this->user->id()
            );
            $bookmarkList = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $bookmarkList, 3600);
        }
        return $bookmarkList;
    }

    public function torrentArtistLeaderboard(\Gazelle\Manager\Artist $artistMan): array {
        self::$db->prepared_query("
            SELECT ta.ArtistID AS id,
                count(*) AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_artists ta USING (GroupID)
            WHERE b.userid = ?
            GROUP BY ta.ArtistID
            ORDER BY total DESC, id
            LIMIT 10
            ", $this->user->id()
        );
        $result = self::$db->to_array(false, MYSQLI_ASSOC, false);
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

    public function torrentArtistTotal(): int {
        return self::$db->scalar("
            SELECT count(*) AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_artists ta USING (GroupID)
            WHERE b.UserID = ?
            ", $this->user->id()
        );
    }

    public function torrentTagLeaderboard(): array {
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
            ", $this->user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function torrentTotal(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM bookmarks_torrents b
            INNER JOIN torrents t USING (GroupID)
            WHERE b.UserID = ?
            ", $this->user->id()
        );
    }

    public function artistList(): array {
        self::$db->prepared_query("
            SELECT ag.ArtistID, ag.Name
            FROM bookmarks_artists AS ba
            INNER JOIN artists_group AS ag USING (ArtistID)
            WHERE ba.UserID = ?
            ORDER BY ag.Name
            ", $this->user->id()
        );
        return self::$db->to_pair('ArtistID', 'Name', false);
    }

    /**
     * Returns an array of torrent bookmarks
     * @return array containing [group_id, seq, added, torrent_id]
     */
    public function torrentList(int $limit, int $offset): array {
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
            ", $this->user->id(), $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Bookmark an object by a user
     *
     * @param string $type (on of artist, collage, request, torrent)
     * @param int $id The ID of the object
     */
    public function create(string $type, int $id): bool {
        [$table, $column] = $this->schema($type);
        if (self::$db->scalar("
            SELECT 1 FROM $table WHERE UserID = ? AND $column = ?
            ", $this->user->id(), $id
        )) {
            // overbooked
            return false;
        }
        switch($type) {
            case 'torrent':
                self::$db->prepared_query("
                    INSERT IGNORE INTO bookmarks_torrents
                           (GroupID,  UserID, Sort)
                    VALUES (?,        ?,
                        (1 + coalesce((SELECT max(m.Sort) from bookmarks_torrents m WHERE m.UserID = ?), 0))
                    )", $id, $this->user->id(), $this->user->id()
                );
                self::$cache->deleteMulti(["u_book_t_" . $this->user->id(), "bookmarks_{$type}_" . $this->user->id(), "bookmarks_group_ids_" . $this->user->id()]);

                $torMan = (new \Gazelle\Manager\Torrent)->setViewer($this->user);
                $tgroup = (new \Gazelle\Manager\TGroup)->findById($id);
                $tgroup->stats()->increment('bookmark_total');

                // RSS feed stuff
                $Feed = new \Feed;
                $list  = $tgroup->torrentList();
                foreach ($list as $t) {
                    $torrent = $torMan->findById($t['ID']);
                    if (is_null($torrent)) {
                        continue;
                    }
                    $Feed->populate('torrents_bookmarks_t_' . $this->user->announceKey(),
                        $Feed->item(
                            $torrent->name() . ' ' . '[' . $torrent->label() .']',
                            \Text::strip_bbcode($tgroup->description()),
                            "torrents.php?action=download&amp;id={$t['ID']}&amp;torrent_pass=[[PASSKEY]]",
                            $this->user->username(),
                            "torrents.php?id=" . $t['ID'],
                            implode(',', $tgroup->tagNameList()),
                        )
                    );
                }
                break;
            case 'request':
                self::$db->prepared_query("
                    INSERT IGNORE INTO bookmarks_requests (RequestID, UserID) VALUES (?, ?)
                    ", $id, $this->user->id()
                );
                self::$cache->delete_value("bookmarks_{$type}_" . $this->user->id());
                $this->updateRequests($id);
                break;
            default:
                self::$db->prepared_query("
                    INSERT IGNORE INTO $table ($column, UserID) VALUES (?, ?)
                    ", $id, $this->user->id()
                );
                self::$cache->delete_value("bookmarks_{$type}_" . $this->user->id());
                break;
        }
        return true;
    }

    /**
     * Remove a bookmark of an object by a user
     *
     * @param string $type (on of artist, collage, request, torrent)
     * @param int $id The ID of the object
     */
    public function remove(string $type, int $id) {
        [$table, $column] = $this->schema($type);
        if (!$id) {
            throw new \Gazelle\Exception\BookmarkIdentifierException($id);
        }
        self::$db->prepared_query("
            DELETE FROM $table WHERE UserID = ?  AND $column = ?
            ", $this->user->id(), $id
        );
        self::$cache->deleteMulti(["u_book_t_" . $this->user->id(), "bookmarks_{$type}_" . $this->user->id()]);

        if (self::$db->affected_rows()) {
            switch ($type) {
            case 'torrent':
                self::$cache->delete_value("bookmarks_group_ids_" . $this->user->id());
                (new \Gazelle\TGroup($id))->stats()->increment('bookmark_total', -1);
                break;
            case 'request':
                $this->updateRequests($id);
            default:
                break;
            }
        }
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
            ", $this->user->id(), $this->user->id()
        );
        self::$cache->delete_value("bookmarks_group_ids_" . $this->user->id());
        return self::$db->affected_rows();
    }

    protected function updateRequests(int $requestId) {
        self::$db->prepared_query("
            SELECT UserID FROM bookmarks_requests WHERE RequestID = ?
            ", $requestId
        );
        if (self::$db->record_count() > 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            \Requests::update_sphinx_requests($requestId);
        } else {
            $SphQL = new \SphinxqlQuery();
            $SphQL->raw_query(
                "UPDATE requests, requests_delta SET bookmarker = ("
                . implode(',', self::$db->collect('UserID'))
                . ") WHERE id = $requestId"
            );
        }
    }
}
