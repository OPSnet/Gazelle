<?php

namespace Gazelle;

class Bookmark extends BaseUser {

    protected array $all;

    /**
     * Get the bookmark schema.
     * Recommended usage:
     * [$table, $column] = $bookmark->schema('torrent');
     *
     * @param string $type the type to get the schema for
     */
    public function schema($type): array {
        switch ($type) {
            case 'torrent':
                return [ 'bookmarks_torrents', 'GroupID' ];
                break;
            case 'artist':
                return [ 'bookmarks_artists', 'ArtistID' ];
                break;
            case 'collage':
                return [ 'bookmarks_collages', 'CollageID' ];
                break;
            case 'request':
                return [ 'bookmarks_requests', 'RequestID' ];
                break;
            default:
                throw new Exception\BookmarkUnknownTypeException($type);
                break;
        }
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
        $all = $this->cache->get_value($key);
        if ($all === false) {
            [$table, $column] = $this->schema($type);
            $q = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT $column
                FROM $table
                WHERE UserID = ?
                ", $this->user->id()
            );
            $all = $this->db->collect($column);
            $this->db->set_query_id($q);
            $this->cache->cache_value($key, $all, 0);
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
    public function torrentBookmarks(): array {
        [$groupIds, $bookmarkData] = $this->cache->get_value("bookmarks_group_ids_" . $this->user->id());
        if (!$groupIds) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT GroupID, Sort, `Time`
                FROM bookmarks_torrents b
                WHERE UserID = ?
                ORDER BY Sort, `Time` ASC
                ", $this->user->id()
            );
            $groupIds = $this->db->collect('GroupID');
            $bookmarkData = $this->db->to_array('GroupID', MYSQLI_ASSOC);
            $this->db->set_query_id($qid);
            $this->cache->cache_value("bookmarks_group_ids_" . $this->user->id(), [$groupIds, $bookmarkData], 3600);
        }
        return [$groupIds, $bookmarkData, \Torrents::get_groups($groupIds)];
    }

    public function torrentArtistLeaderboard(Manager\Artist $artistMan): array {
        $this->db->prepared_query("
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
        $result = $this->db->to_array(false, MYSQLI_ASSOC, false);
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
        return $this->db->scalar("
            SELECT count(*) AS total
            FROM bookmarks_torrents b
            INNER JOIN torrents_artists ta USING (GroupID)
            WHERE b.UserID = ?
            ", $this->user->id()
        );
    }

    public function torrentTagLeaderboard(): array {
        $this->db->prepared_query("
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function torrentTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM bookmarks_torrents b
            INNER JOIN torrents t USING (GroupID)
            WHERE b.UserID = ?
            ", $this->user->id()
        );
    }

    /**
     * Returns an array of torrent bookmarks
     * @return array containing [group_id, seq, added, torrent_id]
     */
    public function torrentList(int $limit, int $offset): array {
        $this->db->prepared_query("
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Bookmark an object by a user
     *
     * @param string $type (on of artist, collage, request, torrent)
     * @param int $id The ID of the object
     */
    public function create(string $type, int $id) {
        [$table, $column] = $this->schema($type);
        if (!$id) {
            throw new Exception\BookmarkIdentifierException($id);
        }
        if ($this->db->scalar("
            SELECT 1 FROM $table WHERE UserID = ? AND $column = ?
            ", $this->user->id(), $id
        )) {
            // overbooked
            return;
        }
        switch($type) {
            case 'torrent':
                $this->db->prepared_query("
                    INSERT IGNORE INTO bookmarks_torrents
                           (GroupID,  UserID, Sort)
                    VALUES (?,        ?,
                        (1 + coalesce((SELECT max(m.Sort) from bookmarks_torrents m WHERE m.UserID = ?), 0))
                    )", $id, $this->user->id(), $this->user->id()
                );
                $this->cache->deleteMulti(["u_book_t_" . $this->user->id(), "bookmarks_{$type}" . $this->user->id(), "bookmarks_group_ids_" . $this->user->id()]);

                $torMan = (new Manager\Torrent)->setViewer($this->user);
                $tgroup = (new Manager\TGroup)->findById($id);

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
                            $tgroup->tagNameList(),
                        )
                    );
                }
                break;
            case 'request':
                $this->db->prepared_query("
                    INSERT IGNORE INTO bookmarks_requests (RequestID, UserID) VALUES (?, ?)
                    ", $id, $this->user->id()
                );
                $this->cache->delete_value("bookmarks_{$type}_" . $this->user->id());
                $this->updateRequests($id);
                break;
            default:
                $this->db->prepared_query("
                    INSERT IGNORE INTO $table ($column, UserID) VALUES (?, ?)
                    ", $id, $this->user->id()
                );
                $this->cache->delete_value("bookmarks_{$type}_" . $this->user->id());
                break;
        }
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
            throw new Exception\BookmarkIdentifierException($id);
        }
        $this->db->prepared_query("
            DELETE FROM $table WHERE UserID = ?  AND $column = ?
            ", $this->user->id(), $id
        );
        $this->cache->delete_value(["u_book_t_" . $this->user->id(), "bookmarks_{$type}_" . $this->user->id()]);

        if ($this->db->affected_rows()) {
            switch ($type) {
            case 'torrent':
                $this->cache->delete_value("bookmarks_group_ids_" . $this->user->id());
                break;
            case 'request':
                $this->updateRequests($id);
            default:
                break;
            }
        }
    }

    protected function updateRequests(int $requestId) {
        $this->db->prepared_query("
            SELECT UserID FROM bookmarks_requests WHERE RequestID = ?
            ", $requestId
        );
        if ($this->db->record_count() > 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            \Requests::update_sphinx_requests($requestId);
        } else {
            $SphQL = new \SphinxqlQuery();
            $SphQL->raw_query(
                "UPDATE requests, requests_delta SET bookmarker = ("
                . implode(',', $this->db->collect('UserID'))
                . ") WHERE id = $requestId"
            );
        }
    }
}
