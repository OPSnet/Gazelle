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
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM torrents_group WHERE ID = ?
                ", $tgroupId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
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
        $id = self::$db->scalar("
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
        $qid = self::$db->get_query_id();

        $voteScore = (int)self::$db->scalar("
            SELECT Score FROM torrents_votes WHERE GroupID = ?
            ", $groupId
        );

        $artistName = (string)self::$db->scalar("
            SELECT group_concat(aa.Name separator ' ')
            FROM torrents_artists AS ta
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ta.Importance IN ('1', '4', '5', '6')
                AND ta.GroupID = ?
            GROUP BY ta.GroupID
            ", $groupId
        );

        self::$db->begin_transaction();
        // todo: remove this legacy code once TagList replacement is confirmed working
        self::$db->prepared_query("
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

        self::$db->prepared_query("
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
        self::$db->commit();
        self::$db->set_query_id($qid);

        self::$cache->deleteMulti([
            sprintf(\Gazelle\TGroup::CACHE_KEY, $groupId),
            sprintf(\Gazelle\TGroup::CACHE_TLIST_KEY, $groupId),
            "groups_artists_$groupId", "torrents_details_$groupId", "torrent_group_$groupId", "torrent_group_light_$groupId"
        ]);
        $info = \Artists::get_artist($groupId);
        foreach ($info as $roles => $role) {
            foreach ($role as $artist) {
                self::$cache->delete_value('artist_groups_' . $artist['id']); //Needed for at least freeleech change, if not others.
            }
        }
    }

    public function merge(\Gazelle\TGroup $old, \Gazelle\TGroup $new, \Gazelle\User $user): bool {
        // Votes ninjutsu. This is so annoyingly complicated.
        // 1. Get a list of everybody who voted on the old group and clear their cache keys
        self::$db->prepared_query("
            SELECT concat('voted_albums_', UserID)
            FROM users_votes
            WHERE GroupID = ?
            ", $old->id()
        );
        self::$cache->deleteMulti(self::$db->collect(0, false));

        self::$db->begin_transaction();

        // 2. Update the existing votes where possible, clear out the duplicates left by key
        // conflicts, and update the torrents_votes table
        self::$db->prepared_query("
            UPDATE IGNORE users_votes SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            DELETE FROM users_votes WHERE GroupID = ?
            ", $old->id()
        );
        self::$db->prepared_query("
            INSERT INTO torrents_votes (GroupId, Ups, Total, Score)
            SELECT                      ?,       Ups, Total, 0
            FROM (
                SELECT
                    ifnull(sum(if(Type = 'Up', 1, 0)), 0) As Ups,
                    count(*) AS Total
                FROM users_votes
                WHERE GroupID = ?
                GROUP BY GroupID
            ) AS a
            ON DUPLICATE KEY UPDATE
                Ups = a.Ups,
                Total = a.Total
            ", $new->id(), $old->id()
        );
        if (self::$db->affected_rows()) {
            // recompute score
            self::$db->prepared_query("
                UPDATE torrents_votes SET
                    Score = IFNULL(binomial_ci(Ups, Total), 0)
                WHERE GroupID = ?
                ", $new->id()
            );
        }

        // 3. Clear the votes_pairs keys
        self::$db->prepared_query("
            SELECT concat('vote_pairs_', v2.GroupId)
            FROM users_votes AS v1
            INNER JOIN users_votes AS v2 USING (UserID)
            WHERE (v1.Type = 'Up' OR v2.Type = 'Up')
                AND (v1.GroupId     IN (?, ?))
                AND (v2.GroupId NOT IN (?, ?))
            ", $old->id(), $new->id(), $old->id(), $new->id()
        );
        self::$cache->deleteMulti(self::$db->collect(0, false));

        // GroupIDs
        self::$db->prepared_query("SELECT ID FROM torrents WHERE GroupID = ?", $old->id());
        $cacheKeys = [];
        while ([$TorrentID] = self::$db->next_row()) {
            $cacheKeys[] = 'torrent_download_' . $TorrentID;
            $cacheKeys[] = 'tid_to_group_' . $TorrentID;
        }
        self::$cache->deleteMulti($cacheKeys);
        unset($cacheKeys);

        self::$db->prepared_query("
            UPDATE torrents SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            UPDATE wiki_torrents SET
                PageID = ?
            WHERE PageID = ?
            ", $new->id(), $old->id()
        );

        (new \Gazelle\Manager\Bookmark)->merge($old->id(), $new->id());
        (new \Gazelle\Manager\Comment)->merge('torrents', $old->id(), $new->id());

        // Collages
        self::$db->prepared_query("
            SELECT CollageID FROM collages_torrents WHERE GroupID = ?
            ", $old->id()
        );
        $collageList = self::$db->collect(0, false);
        self::$db->prepared_query("
            UPDATE IGNORE collages_torrents SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            DELETE FROM collages_torrents WHERE GroupID = ?
                ", $old->id()
        );
        self::$cache->deleteMulti(array_map(
            fn ($id) => sprintf(\Gazelle\Collage::CACHE_KEY, $id), $collageList
        ));

        // Requests
        self::$db->prepared_query("
            SELECT concat('request_', ID) FROM requests WHERE GroupID = ?
            ", $old->id()
        );
        self::$cache->deleteMulti(self::$db->collect(0, false));
        self::$db->prepared_query("
            UPDATE requests SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );

        self::$db->prepared_query("
            UPDATE group_log SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );

        $old->remove($user, $log);
        self::$db->commit();

        $this->refresh($new->id());

        self::$cache->deleteMulti([
            "requests_group_" . $new->id(),
            "torrent_collages_" . $new->id(),
            "torrent_collages_personal_" . $new->id(),
            "votes_" . $new->id(),
        ]);
        return true;
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
        if (!($latest = self::$cache->get_value(self::CACHE_KEY_LATEST_UPLOADS . $limit))) {
            self::$db->prepared_query("
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
                $row = self::$db->next_record(MYSQLI_ASSOC, false);
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
            self::$cache->cache_value(self::CACHE_KEY_LATEST_UPLOADS . $limit, $latest, 86400);
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
        self::$cache->delete_value(self::CACHE_KEY_LATEST_UPLOADS . $limit);
    }

    protected function featuredAlbum(int $type): array {
        $key = sprintf(self::CACHE_KEY_FEATURED, $type);
        if (($featured = self::$cache->get_value($key)) === false) {
            $featured = self::$db->rowAssoc("
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
            self::$cache->cache_value($key, $featured, 86400 * 7);
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
