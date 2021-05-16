<?php

namespace Gazelle\Manager;

class TGroup extends \Gazelle\Base {

    const CACHE_KEY_FEATURED       = 'featured_%d';
    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';

    const FEATURED_AOTM     = 0;
    const FEATURED_SHOWCASE = 1;

    public function findById(int $groupId) {
        $id = $this->db->scalar("
            SELECT ID FROM torrents_group WHERE ID = ?
            ", $groupId
        );
        return $id ? new \Gazelle\TGroup($id) : null;
    }

    /**
     * Map a torrenthash to a group id
     * @param string $hash
     * @return int The group id if found, otherwise null
     */
    public function findByTorrentInfohash(string $hash) {
        $id = $this->db->scalar("
            SELECT GroupID FROM torrents WHERE info_hash = UNHEX(?)
            ", $hash
        );
        return $id ? new \Gazelle\TGroup($id) : null;
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
                    R.GroupID       AS groupId,
                    R.torrentId,
                    R.uploadDate,
                    um.Username     AS username,
                    um.Paranoia     AS paranoia,
                    group_concat(tag.Name ORDER BY tag.Name SEPARATOR ', ') AS tags
                FROM (
                    SELECT t.GroupID,
                        max(t.ID)   AS torrentId,
                        max(t.Time) AS uploadDate
                    FROM torrents t
                    INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                    WHERE t.Time > now() - INTERVAL 3 DAY
                        AND t.Encoding IN ('Lossless', '24bit Lossless')
                        AND tg.WikiImage != ''
                        AND NOT EXISTS (
                            SELECT 1
                            FROM torrents_tags ttex
                            WHERE t.GroupID = ttex.GroupID
                                AND ttex.TagID IN (" . placeholders(HOMEPAGE_TAG_IGNORE) . ")
                        )
                    GROUP BY t.GroupID
                ) R
                INNER JOIN torrents_group tg ON (tg.ID = R.groupId)
                INNER JOIN torrents_tags  tt USING (GroupID)
                INNER JOIN tags           tag ON (tag.ID = tt.TagID)
                INNER JOIN torrents       t   ON (t.ID = R.torrentId)
                INNER JOIN users_main     um  ON (um.ID = t.UserID)
                GROUP BY R.GroupID
                ORDER BY R.uploadDate DESC
                ", ...HOMEPAGE_TAG_IGNORE
            );
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

    protected function featuredAlbum(int $type): array {
        $key = sprintf(self::CACHE_KEY_FEATURED, $type);
        if (($featured = $this->cache->get_value($key)) === false) {
            $featured = $this->db->rowAssoc("
                SELECT fa.GroupID,
                    tg.Name,
                    tg.WikiImage,
                    fa.ThreadID,
                    fa.Title
                FROM featured_albums AS fa
                INNER JOIN torrents_group AS tg ON (tg.ID = fa.GroupID)
                WHERE Ended IS NULL AND type = ?
                ", $type
            );
            if (!is_null($featured)) {
                $featured['artist_name'] = \Artists::display_artists(\Artists::get_artist($featured['GroupID']), false, false);
                $featured['image']       = \ImageTools::process($featured['WikiImage'], true);
            }
            $this->cache->cache_value($key, $featured, 86400 * 7);
        }
        return $featured ?? [];
    }

    public function featuredAlbumAotm(): array {
        return $this->featuredAlbum(self::FEATURED_AOTM);
    }

    public function featuredAlbumShowcase(): array {
        return $this->featuredAlbum(self::FEATURED_SHOWCASE);
    }
}
