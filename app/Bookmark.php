<?php

namespace Gazelle;

class Bookmark extends Base {

    /**
     * Get the bookmark schema.
     * Recommended usage:
     * [$table, $column] = $bookmark->schema('torrent');
     *
     * @param string $type the type to get the schema for
     * @return [table, column]
     */
    public function schema($type) {
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
     *
     * @param string $type
     *            type of bookmarks to fetch
     * @param int $userID
     *            userid whose bookmarks to get
     * @return array the bookmarks
     */
    public function allBookmarks(string $type, $userId) {
        $key = "bookmarks_{$type}_{$userId}";
        if (($all = $this->cache->get_value($key)) === false) {
            list ($table, $column) = $this->schema($type);
            $q = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT $column
                FROM $table
                WHERE UserID = ?
                ", $userId
            );
            $all = $this->db->collect($column);
            $this->db->set_query_id($q);
            $this->cache->cache_value($key, $all, 0);
        }
        return $all;
    }


    /**
     * Returns an array with User Bookmark data: group IDs, collage data, torrent data
     * @param int $UserID
     * @return array Group IDs, Bookmark Data, Torrent List
     */
    public function torrentBookmarks(int $userId) {
        [$groupIds, $bookmarkData] = $this->cache->get_value("bookmarks_group_ids_$userId");
        if (!$groupIds) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT GroupID, Sort, `Time`
                FROM bookmarks_torrents
                WHERE UserID = ?
                ORDER BY Sort, `Time` ASC
                ", $userId
            );
            $groupIds = $this->db->collect('GroupID');
            $bookmarkData = $this->db->to_array('GroupID', MYSQLI_ASSOC);
            $this->db->set_query_id($qid);
            $this->cache->cache_value("bookmarks_group_ids_$userId", [$groupIds, $bookmarkData], 3600);
        }
        return [$groupIds, $bookmarkData, \Torrents::get_groups($groupIds)];
    }

    /**
     * Check if something is bookmarked
     *
     * @param int $userId  ID of user
     * @param string $type Type of bookmarks to check
     * @param int $id      bookmark id
     * @return boolean
     */
    protected function isBookmarked(int $userId, string $type, int $id) {
        return in_array($id, $this->allBookmarks($type, $userId));
    }

    /**
     * Check if an artist is bookmarked by a user
     * @param int $userId User id
     * @param int $id     Artist id
     * @return boolean
     */
    public function isArtistBookmarked(int $userId, int $id) {
        return in_array($id, $this->allBookmarks('artist', $userId));
    }

    /**
     * Check if a collage is bookmarked by a user
     * @param int $userId User id
     * @param int $id     Collage id
     * @return boolean
     */
    public function isCollageBookmarked(int $userId, int $id) {
        return in_array($id, $this->allBookmarks('collage', $userId));
    }

    /**
     * Check if a request is bookmarked by a user
     * @param int $userId User id
     * @param int $id     Request id
     * @return boolean
     */
    public function isRequestBookmarked(int $userId, int $id) {
        return in_array($id, $this->allBookmarks('request', $userId));
    }

    /**
     * Check if an torrent is bookmarked by a user
     * @param int $userId User id
     * @param int $id     Torrent id
     * @return boolean
     */
    public function isTorrentBookmarked(int $userId, int $id) {
        return in_array($id, $this->allBookmarks('torrent', $userId));
    }

    /**
     * Bookmark an object by a user
     *
     * @param int $userID The ID of the user
     * @param string (on of artist, collage, request, torrent)
     * @param int $id The ID of the object
     */
    public function create(int $userId, string $type, int $id) {
        [$table, $column] = $this->schema($type);
        if (!$id) {
            throw new Exception\BookmarkIdentifierException($id);
        }
        if ($this->db->scalar("
            SELECT 1 FROM $table WHERE UserID = ? AND $column = ?
            ", $userId, $id
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
                    )", $id, $userId, $userId
                );
                $this->cache->deleteMulti(["bookmarks_{$type}_{$userId}", "bookmarks_group_ids_{$userId}"]);

                $user   = (new Manager\User)->findById($userId);
                $torMan = (new Manager\Torrent)->setViewer($user);
                $tgroup = (new Manager\TGroup)->findById($id);

                // RSS feed stuff
                $Feed = new \Feed;
                $list  = $tgroup->torrentList();
                foreach ($list as $t) {
                    $torrent = $torMan->findById($t['ID']);
                    if (is_null($torrent)) {
                        continue;
                    }
                    $Feed->populate('torrents_bookmarks_t_' . $user->announceKey(),
                        $Feed->item(
                            $torrent->name() . ' ' . '[' . $torrent->label() .']',
                            \Text::strip_bbcode($tgroup()->description()),
                            "torrents.php?action=download&amp;id={$t['ID']}&amp;torrent_pass=[[PASSKEY]]",
                            $user->username(),
                            "torrents.php?id=" . $t['ID'],
                            $tgroup->tagNameList(),
                        )
                    );
                }
                break;
            case 'request':
                $this->db->prepared_query("
                    INSERT IGNORE INTO bookmarks_requests (RequestID, UserID) VALUES (?, ?)
                    ", $id, $userId
                );
                $this->cache->delete_value("bookmarks_{$type}_{$userId}");
                $this->updateRequests($id);
                break;
            default:
                $this->db->prepared_query("
                    INSERT IGNORE INTO $table ($column, UserID) VALUES (?, ?)
                    ", $id, $userId
                );
                $this->cache->delete_value("bookmarks_{$type}_{$userId}");
                break;
        }
    }

    /**
     * Remove a bookmark of an object by a user
     *
     * @param int $userID The ID of the user
     * @param string (on of artist, collage, request, torrent)
     * @param int $id The ID of the object
     */
    public function remove(int $userId, string $type, int $id) {
        [$table, $column] = $this->schema($type);
        if ($id < 1) {
            throw new Exception\BookmarkIdentifierException($id);
        }
        $this->db->prepared_query("
            DELETE FROM $table WHERE UserID = ?  AND $column = ?
            ", $userId, $id
        );
        $this->cache->delete_value("bookmarks_{$type}_{$userId}");

        if ($this->db->affected_rows()) {
            switch ($type) {
            case 'torrent':
                $this->cache->delete_value("bookmarks_group_ids_$userId");
                break;
            case 'request':
                $this->updateRequests($id);
            default:
                break;
            }
        }
    }

    protected function updateRequests(int $id) {
        $this->db->prepared_query("
            SELECT UserID FROM bookmarks_requests WHERE RequestID = ?
            ", $id
        );
        if ($this->db->record_count() > 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            \Requests::update_sphinx_requests($id);
        } else {
            $SphQL = new \SphinxqlQuery();
            $SphQL->raw_query(
                "UPDATE requests, requests_delta SET bookmarker = ("
                . implode(',', $this->db->collect('UserID'))
                . ") WHERE id = $id"
            );
        }
    }
}
