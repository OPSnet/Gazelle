<?php

namespace Gazelle\Manager;

class Torrent extends \Gazelle\Base {

    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';

    /**
     * Is this a valid torrenthash?
     * @param string $hash
     * @return string|bool The hash (with any spaces removed) if valid, otherwise false
     */
    public function isValidHash(string $hash) {
        //6C19FF4C 6C1DD265 3B25832C 0F6228B2 52D743D5
        $hash = str_replace(' ', '', $hash);
        return preg_match('/^[0-9a-fA-F]{40}$/', $hash) ? $hash : false;
    }

    /**
     * Map a torrenthash to a torrent id
     * @param string $hash
     * @return int The torrent id if found, otherwise null
     */
    public function hashToTorrentId(string $hash) {
        return $this->db->scalar("
            SELECT ID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a group id
     * @param string $hash
     * @return int The group id if found, otherwise null
     */
    public function hashToGroupId(string $hash) {
        return $this->db->scalar("
            SELECT GroupID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a torrent id and its group id
     * @param string $hash
     * @return array The torrent id and group id if found, otherwise null
     */
    public function hashToTorrentGroup(string $hash) {
        return $this->db->row("
            SELECT ID, GroupID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrent id to a group id
     * @param int $torrentId
     * @return int The group id if found, otherwise null
     */
    public function idToGroupId(int $torrentId) {
        return $this->db->scalar("
            SELECT GroupID
            FROM torrents
            WHERE ID = ?
            ", $torrentId
        );
    }

    /**
     * Return the N most recent lossless uploads
     * Note that if both a Lossless and 24bit Lossless are uploaded at the same time,
     * only one entry will be returned, to ensure that the result is comprised of N
     * different groups. Uploads of paranoid users are excluded. Uploads without
     * cover art are excluded.
     *
     * @param int $limit
     * @return array of [imageUrl, groupId, torrentId, uploadDate, username, paranoia]
     */
    public function latestUploads(int $limit) {
        if (!($latest = $this->cache->get_value(self::CACHE_KEY_LATEST_UPLOADS . $limit))) {
            $this->db->prepared_query("
                SELECT tg.WikiImage AS imageUrl,
                    tg.ID           AS groupId,
                    t.ID            AS torrentId,
                    t.Time          AS uploadDate,
                    um.Username     AS username,
                    um.Paranoia     AS paranoia,
                    group_concat(tag.Name ORDER BY tag.Name SEPARATOR ', ') AS tags
                FROM torrents t
                /* Mysql cannot filter and sort from the same index, so help it - Spine */
                INNER JOIN (SELECT ID FROM torrents ORDER BY Time DESC LIMIT 100) Recent ON (Recent.ID = t.ID)
                INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                INNER JOIN users_main     um ON (um.ID = t.UserID)
                INNER JOIN torrents_tags  tt USING (GroupID)
                INNER JOIN tags           tag ON (tag.ID = tt.TagID)
                WHERE t.Encoding IN ('Lossless', '24bit Lossless')
                    AND tg.WikiImage != ''
                GROUP BY tg.ID
                ORDER BY t.Time DESC
            ");
            $latest = [];
            while (count($latest) < $limit) {
                $row = $this->db->next_record(MYSQLI_ASSOC, false);
                if (!$row) {
                    break;
                }
                if (isset($latest[$row['groupId']])) {
                    continue;
                } else {
                    $paranoia = unserialize($row['paranoia']);
                    if (is_array($paranoia) && in_array('uploads', $paranoia)) {
                        continue;
                    }
                }
                $row['name'] = \Torrents::display_string($row['groupId'], \Torrents::DISPLAYSTRING_SHORT);
                $latest[$row['groupId']] = $row;
            }
            $this->cache->cache_value(self::CACHE_KEY_LATEST_UPLOADS . $limit, $latest, 86400);
        }
        return $latest;
    }

    /**
     * Flush the most recent uploads (e.g. a new lossless upload is made).
     *
     * Note: Since arbitrary N may have been cached, all uses of N latest
     * uploads must be flushed when invalidating, following a new upload.
     * grep is your friend. This also assumes that there is sufficient
     * activity to not have to worry about a very recent upload being
     * deleted for whatever reason. For a start, even if the list becomes
     * briefly out of date, the next upload will regenerate the list.
     *
     * @param int $limit
     */
    public function flushLatestUploads(int $limit) {
        $this->cache->delete_value(self::CACHE_KEY_LATEST_UPLOADS . $limit);
    }
}
