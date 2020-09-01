<?php

namespace Gazelle\Manager;

class Torrent extends \Gazelle\Base {

    protected $userId;
    protected $showSnatched;
    protected $snatchBucket;
    protected $updateTime;
    protected $tokenCache;

    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';
    const FILELIST_DELIM = 0xF7; // Hex for &divide; Must be the same as phrase_boundary in sphinx.conf!
    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists
    const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    /**
     * Set context to a specific user. Used to determine whether or not to display
     * Personal Freeleech and Snatched indicators in torrent and group info.
     *
     * @param int $userID The ID of the User
     * @return $this to allow method chaining
     */
    public function setViewer(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    /**
     * In the context of a user, determine whether snatched indicators should be
     * added to torrent and group info.
     *
     * @param int $userID The ID of the User
     * @return $this to allow method chaining
     */
    public function setShowSnatched(int $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    /**
     * Has the viewing user snatched this torrent? (And do they want
     * to know about it?)
     *
     * @param int $torrentId
     * @return bool
     */
    public function isSnatched(int $torrentId): bool {
        if (!$this->userId && !$this->showSnatched) {
            return false;
        }

        $buckets = 64;
        $bucketMask = $buckets - 1;
        $bucketId = $torrentId & $bucketMask;

        $snatchKey = "users_snatched_" . $this->$userID . "_time";
        if (!$this->snatchBucket) {
            $this->snatchBucket = array_fill(0, $buckets, false);
            $this->updateTime = $this->cache->get_value($snatchKey);
            if ($this->updateTime === false) {
                $this->updateTime = [
                    'last' => 0,
                    'next' => 0
                ];
            }
        } elseif (isset($this->snatchBucket[$bucketId][$torrentId])) {
            return true;
        }

        // Torrent was not found in the previously inspected snatch lists
        $bucket =& $this->snatchBucket[$bucketId];
        if ($bucket === false) {
            $now = time();
            // This bucket hasn't been checked before
            $bucket = $this->cache->get_value($snatchKey, true);
            if ($bucket === false || $now > $this->updateTime['next']) {
                $bucketKeyStem = 'users_snatched_' . $this->userId . '_';
                $updated = [];
                $qid = $this->db->get_query_id();
                if ($bucket === false || $this->updateTime['last'] == 0) {
                    for ($i = 0; $i < $buckets; $i++) {
                        $this->snatchBucket[$i] = [];
                    }
                    // Not found in cache. Since we don't have a suitable index, it's faster to update everything
                    $this->db->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ?
                        ", $UserID
                    );
                    while ([$id] = $this->db->next_record(MYSQLI_NUM, false)) {
                        $this->snatchBucket[$id & $bucketMask][(int)$id] = true;
                    }
                    $updated = array_fill(0, $buckets, true);
                } elseif (isset($bucket[$torrentId])) {
                    // Old cache, but torrent is snatched, so no need to update
                    return true;
                } else {
                    // Old cache, check if torrent has been snatched recently
                    $this->db->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ? AND tstamp >= ?
                        ", $this->userId, $this->updateTime['last']
                    );
                    while ([$id] = $this->db->next_record(MYSQLI_NUM, false)) {
                        $bucketId = $id & $bucketMask;
                        if ($this->snatchBucket[$bucketId] === false) {
                            $this->snatchBucket[$bucketId] = $this->cache->get_value("$bucketKeyStem$bucketId", true);
                            if ($this->snatchBucket[$bucketId] === false) {
                                $this->snatchBucket[$bucketId] = [];
                            }
                        }
                        $this->snatchBucket[$bucketId][(int)$id] = true;
                        $updated[$bucketId] = true;
                    }
                }
                $this->db->set_query_id($qid);
                for ($i = 0; $i < $buckets; $i++) {
                    if (isset($updated[$i])) {
                        $this->cache->cache_value("$bucketKeyStem$i", $this->snatchBucket[$i], 7200);
                    }
                }
                $this->updateTime['last'] = $now;
                $this->updateTime['next'] = $now + self::SNATCHED_UPDATE_INTERVAL;
                $this->cache->cache_value($snatchKey, $this->updateTime, 7200);
            }
        }
        return isset($bucket[$torrentId]);
    }

    /**
     * Check if the viwer has an active freeleech token
     * setViewer() must be called beforehand
     *
     * @param int $torrentId
     * @return true if an active token exists for the viewer
     */
    public function hasToken(int $torrentId): bool {
        if (!$this->userId) {
            return false;
        }
        if (!$this->tokenCache) {
            $key = "users_tokens_" . $this->userId;
            $this->tokenCache = $this->cache->get_value($key);
            if ($this->tokenCache === false) {
                $qid = $this->db->get_query_id();
                $this->db->prepared_query("
                    SELECT TorrentID
                    FROM users_freeleeches
                    WHERE Expired = 0 AND UserID = ?
                    ",
                    $this->userId
                );
                $this->tokenCache = array_fill_keys($this->db->collect('TorrentID', false), true);
                $this->db->set_query_id($qid);
                $this->cache->cache_value($key, $this->tokenCache, 3600);
            }
        }
        return isset($this->tokenCache[$torrentId]);
    }

    public function groupInfo(int $groupId, int $revisionId = 0): ?array {
        if (!$groupId) {
            return null;
        }
        if (!$revisionId) {
            $cached = $this->cache->get_value("torrents_details_$groupId");
        }
        if (!$revisionId && is_array($cached)) {
            [$group, $torrentList] = $cached;
        } else {
            // Fetch the group details

            $SQL = 'SELECT '
                . ($revisionId ? 'w.Body, w.Image,' : 'g.WikiBody, g.WikiImage,')
                . " g.ID,
                    g.Name,
                    g.Year,
                    g.RecordLabel,
                    g.CatalogueNumber,
                    g.ReleaseType,
                    g.CategoryID,
                    g.Time,
                    g.VanityHouse,
                    GROUP_CONCAT(DISTINCT tags.Name SEPARATOR '|') as tagNames,
                    GROUP_CONCAT(DISTINCT tags.ID SEPARATOR '|') as tagIds,
                    GROUP_CONCAT(tt.UserID SEPARATOR '|') as tagVoteUserIds,
                    GROUP_CONCAT(tt.PositiveVotes SEPARATOR '|') as tagUpvotes,
                    GROUP_CONCAT(tt.NegativeVotes SEPARATOR '|') as tagDownvotes
                FROM torrents_group AS g
                LEFT JOIN torrents_tags AS tt ON (tt.GroupID = g.ID)
                LEFT JOIN tags ON (tags.ID = tt.TagID)";

            $args = [];
            if ($revisionId) {
                $SQL .= '
                    LEFT JOIN wiki_torrents AS w ON (w.PageID = ? AND w.RevisionID = ?)';
                $args[] = $groupId;
                $args[] = $revisionId;
            }

            $SQL .= "
                WHERE g.ID = ?
                GROUP BY g.ID";
            $args[] = $groupId;

            $this->db->prepared_query($SQL, ...$args);
            $group = $this->db->next_record(MYSQLI_ASSOC);

            // Fetch the individual torrents
            $columns = "
                    t.ID,
                    t.Media,
                    t.Format,
                    t.Encoding,
                    t.Remastered,
                    t.RemasterYear,
                    t.RemasterTitle,
                    t.RemasterRecordLabel,
                    t.RemasterCatalogueNumber,
                    t.Scene,
                    t.HasLog,
                    t.HasCue,
                    t.HasLogDB,
                    t.LogScore,
                    t.LogChecksum,
                    t.FileCount,
                    t.Size,
                    tls.Seeders,
                    tls.Leechers,
                    tls.Snatched,
                    t.FreeTorrent,
                    t.Time,
                    t.Description,
                    t.FileList,
                    t.FilePath,
                    t.UserID,
                    tls.last_action,
                    HEX(t.info_hash) AS InfoHash,
                    tbt.TorrentID AS BadTags,
                    tbf.TorrentID AS BadFolders,
                    tfi.TorrentID AS BadFiles,
                    ml.TorrentID AS MissingLineage,
                    ca.TorrentID AS CassetteApproved,
                    lma.TorrentID AS LossymasterApproved,
                    lwa.TorrentID AS LossywebApproved,
                    t.LastReseedRequest,
                    t.ID AS HasFile,
                    COUNT(tl.LogID) AS LogCount
            ";

            $this->db->prepared_query("
                SELECT $columns, 0 as is_deleted
                FROM torrents AS t
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                LEFT JOIN torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
                LEFT JOIN torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
                LEFT JOIN torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
                LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
                LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
                LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
                LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
                LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
                WHERE t.GroupID = ?
                GROUP BY t.ID
                UNION DISTINCT
                SELECT $columns, 1 as is_deleted
                FROM deleted_torrents AS t
                INNER JOIN deleted_torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
                LEFT JOIN deleted_torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
                LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
                WHERE t.GroupID = ?
                GROUP BY t.ID
                ORDER BY Remastered ASC,
                    (RemasterYear != 0) DESC,
                    RemasterYear ASC,
                    RemasterTitle ASC,
                    RemasterRecordLabel ASC,
                    RemasterCatalogueNumber ASC,
                    Media ASC,
                    Format,
                    Encoding,
                    ID", $groupId, $groupId);

            $torrentList = $this->db->to_array('ID', MYSQLI_ASSOC);
            if (empty($group) || empty($torrentList)) {
                return null;
            }

            if (!$revisionId) {
                $this->cache->cache_value("torrents_details_$groupId", [$group, $torrentList],
                    in_array(0, $this->db->collect('Seeders')) ? 600 : 3600
                );
            }
        }

        // Fetch all user specific torrent and group properties
        $group['Flags'] = ['IsSnatched' => false];
        foreach ($torrentList as &$t) {
            $t['PersonalFL'] = empty($t['FreeTorrent']) && $this->hasToken($t['ID']);
            if ($t['IsSnatched'] = $this->isSnatched($t['ID'])) {
                $group['IsSnatched'] = true;
            }
        }
        unset($t);

        // make the values sane (null, boolean as appropriate)
        // TODO: once all get_*_info calls have been ported over, do this prior to caching
        foreach (['CatalogueNumber', 'RecordLabel'] as $nullable) {
            $group[$nullable] = $group[$nullable] == '' ? null : $group[$nullable];
        }
        if (!$group['WikiImage']) {
            global $CategoryIcons;
            $group['WikiImage'] = STATIC_SERVER.'common/noartwork/'
                . $CategoryIcons[$group['CategoryID'] - 1];
        }
        $group['VanityHouse'] = ($group['VanityHouse'] == 1);

        // Reorganize tag info to be useful
        $tagIds = explode('|', $group['tagIds']);
        $tagNames = explode('|', $group['tagNames']);
        $tagVoteUserIds = explode('|', $group['tagVoteUserIds']);
        $tagUpvotes = explode('|', $group['tagUpvotes']);
        $tagDownvotes = explode('|', $group['tagDownvotes']);
        $group['tags'] = [];
        for ($n = 0; $n < count($tagIds); ++$n) {
            $group['tags'][$tagIds[$n]] = [
                'name' => $tagNames[$n],
                'userId' => $tagVoteUserIds[$n],
                'upvotes' => $tagUpvotes[$n],
                'downvotes' => $tagDownvotes[$n],
            ];
        }

        foreach ($torrentList as &$torrent) {
            foreach (['last_action', 'LastReseedRequest', 'RemasterCatalogueNumber', 'RemasterRecordLabel', 'RemasterTitle', 'RemasterYear']
                as $nullable
            ) {
                $torrent[$nullable] = $torrent[$nullable] == '' ? null : $torrent[$nullable];
            }
            foreach (['FreeTorrent', 'HasCue', 'HasLog', 'HasLogDB', 'LogChecksum', 'Remastered', 'Scene']
                as $zerotruth
            ) {
                $torrent[$zerotruth] = !($torrent[$zerotruth] == '0');
            }
            foreach (['BadFiles', 'BadFolders', 'BadTags', 'CassetteApproved', 'LossymasterApproved', 'LossywebApproved', 'MissingLineage']
                as $emptytruth
            ) {
                $torrent[$emptytruth] = !($torrent[$emptytruth] == '');
            }
        }

        return [$group, $torrentList];
    }

    public function torrentInfo(int $torrentId, $revisionId = 0) {
        $groupId = $this->idToGroupId($torrentId);
        if (!$groupId) {
            return null;
        }
        if (!($info = $this->groupInfo($groupId, $revisionId))) {
            return null;
        }
        return [$info[0], $info[1][$torrentId]];
    }

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
