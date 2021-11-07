<?php

namespace Gazelle\Manager;

class TGroup extends \Gazelle\Base {

    protected const ID_KEY = 'zz_tg_%d';

    const CACHE_KEY_FEATURED       = 'featured_%d';
    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';

    const FEATURED_AOTM     = 0;
    const FEATURED_SHOWCASE = 1;

    protected \Gazelle\User $viewer;

    /**
     * Set the viewer context, for snatched indicators etc.
     * If this is set, and Torrent object created will have it set
     */
    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    public function findById(int $tgroupId): ?\Gazelle\TGroup {
        $key = sprintf(self::ID_KEY, $tgroupId);
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT ID FROM torrents_group WHERE ID = ?
                ", $tgroupId
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        if (!$id) {
            return null;
        }
        $tgroup = new \Gazelle\TGroup($id);
        if (isset($this->viewer)) {
            $tgroup->setViewer($this->viewer);
        }
        return $tgroup;
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
     * Update the cache and sphinx delta index to keep everything up-to-date.
     *
     * @param int groupId
     */
    public function refresh(int $groupId) {
        $qid = $this->db->get_query_id();

        $voteScore = (int)$this->db->scalar("
            SELECT Score FROM torrents_votes WHERE GroupID = ?
            ", $groupId
        );

        $artistName = (string)$this->db->scalar("
            SELECT group_concat(aa.Name separator ' ')
            FROM torrents_artists AS ta
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ta.Importance IN ('1', '4', '5', '6')
                AND ta.GroupID = ?
            GROUP BY ta.GroupID
            ", $groupId
        );

        $this->db->begin_transaction();
        // todo: remove this legacy code once TagList replacement is confirmed working
        $this->db->prepared_query("
            UPDATE torrents_group SET
                TagList = (
                    SELECT REPLACE(GROUP_CONCAT(tags.Name SEPARATOR ' '), '.', '_')
                    FROM torrents_tags AS t
                    INNER JOIN tags ON (tags.ID = t.TagID)
                    WHERE t.GroupID = ?
                    GROUP BY t.GroupID
                )
            WHERE ID = ?
            ", $groupId, $groupId
        );

        $this->db->prepared_query("
            REPLACE INTO sphinx_delta
                (ID, GroupID, GroupName, Year, CategoryID, Time, ReleaseType, RecordLabel,
                CatalogueNumber, VanityHouse, Size, Snatched, Seeders, Leechers, LogScore, Scene, HasLog,
                HasCue, FreeTorrent, Media, Format, Encoding, Description, RemasterYear, RemasterTitle,
                RemasterRecordLabel, RemasterCatalogueNumber, FileList, TagList, VoteScore, ArtistName)
            SELECT
                t.ID, g.ID, g.Name, g.Year, g.CategoryID, unix_timestamp(t.Time), g.ReleaseType,
                g.RecordLabel, g.CatalogueNumber, g.VanityHouse, t.Size, tls.Snatched, tls.Seeders,
                tls.Leechers, t.LogScore, cast(t.Scene AS CHAR), cast(t.HasLog AS CHAR), cast(t.HasCue AS CHAR),
                cast(t.FreeTorrent AS CHAR), t.Media, t.Format, t.Encoding, t.Description,
                t.RemasterYear, t.RemasterTitle, t.RemasterRecordLabel, t.RemasterCatalogueNumber,
                replace(replace(t.FileList, '_', ' '), '/', ' ') AS FileList,
                replace(group_concat(t2.Name SEPARATOR ' '), '.', '_'), ?, ?
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            INNER JOIN torrents_group g ON (g.ID = t.GroupID)
            INNER JOIN torrents_tags tt ON (tt.GroupID = g.ID)
            INNER JOIN tags t2 ON (t2.ID = tt.TagID)
            WHERE g.ID = ?
            GROUP BY t.ID
            ", $voteScore, $artistName, $groupId
        );
        $this->db->commit();
        $this->db->set_query_id($qid);

        $this->cache->deleteMulti([
            "tg2_$groupId", "groups_artists_$groupId", "torrents_details_$groupId", "torrent_group_$groupId", "torrent_group_light_$groupId"
        ]);
        $info = \Artists::get_artist($groupId);
        foreach ($info as $roles => $role) {
            foreach ($role as $artist) {
                $this->cache->delete_value('artist_groups_' . $artist['id']); //Needed for at least freeleech change, if not others.
            }
        }
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
                global $Viewer; // FIXME this wrong
                $featured['artist_name'] = \Artists::display_artists(\Artists::get_artist($featured['GroupID']), false, false);
                $featured['image']       = (new \Gazelle\Util\ImageProxy)->setViewer($Viewer)->process($featured['WikiImage']);
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
