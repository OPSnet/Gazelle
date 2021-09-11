<?php

namespace Gazelle;

class Torrent extends BaseObject {

    const CACHE_KEY                = 't_%d';
    const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';

    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists

    protected $isDeleted = false;
    protected $showSnatched;
    protected $snatchBucket;
    protected $tokenCache;
    protected $updateTime;
    protected $viewerId;

    public function tableName(): string {
        return 'torrents';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function flush() {
    }

    /**
     * Set the viewer context, for snatched indicators etc.
     *
     * @param int $userID The ID of the User
     * @return $this to allow method chaining
     */
    public function setViewerId(int $viewerId) {
        $this->viewerId = $viewerId;
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
     * Check if the viewer has an active freeleech token on a torrent
     *
     * @param int userId
     * @return true if an active token exists for the viewer
     */
    public function hasToken(int $userId): bool {
        if (!$this->tokenCache) {
            $key = "users_tokens_" . $userId;
            $this->tokenCache = $this->cache->get_value($key);
            if ($this->tokenCache === false) {
                $qid = $this->db->get_query_id();
                $this->db->prepared_query("
                    SELECT TorrentID FROM users_freeleeches WHERE Expired = 0 AND UserID = ?
                    ", $userId
                );
                $this->tokenCache = array_fill_keys($this->db->collect('TorrentID', false), true);
                $this->db->set_query_id($qid);
                $this->cache->cache_value($key, $this->tokenCache, 3600);
            }
        }
        return isset($this->tokenCache[$this->id]);
    }

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(): array {
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = $this->cache->get_value($key);
        $info = false;
        if ($info === false) {
            $template = "SELECT t.GroupID, t.UserID, t.Media, t.Format, t.Encoding,
                    t.Remastered, t.RemasterYear, t.RemasterTitle, t.RemasterCatalogueNumber, t.RemasterRecordLabel,
                    t.Scene, t.HasLog, t.HasCue, t.HasLogDB, t.LogScore, t.LogChecksum,
                    hex(t.info_hash) as info_hash, t.FileCount, t.FileList, t.FilePath, t.Size,
                    t.FreeTorrent, t.FreeLeechType, t.Time, t.Description, t.LastReseedRequest,
                    tls.Seeders, tls.Leechers, tls.Snatched, tls.last_action,
                    tbt.TorrentID AS BadTags, tbf.TorrentID AS BadFolders, tfi.TorrentID AS BadFiles, ml.TorrentID  AS MissingLineage,
                    ca.TorrentID  AS CassetteApproved, lma.TorrentID AS LossymasterApproved, lwa.TorrentID AS LossywebApproved,
                    group_concat(tl.LogID) as ripLogIds
                FROM %table% t
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                LEFT JOIN torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
                LEFT JOIN torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
                LEFT JOIN torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
                LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
                LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
                LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
                LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
                LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
                WHERE t.ID = ?
                GROUP BY t.ID
            ";
            $info = $this->db->rowAssoc(str_replace('%table%', 'torrents', $template), $this->id);
            if (is_null($info)) {
                $info = $this->db->rowAssoc(str_replace('%table%', 'deleted_torrents', $template), $this->id);
                $this->isDeleted = true;
            }
            if (is_null($info)) {
                return [];
            }
            foreach (['last_action', 'LastReseedRequest', 'RemasterCatalogueNumber', 'RemasterRecordLabel', 'RemasterTitle', 'RemasterYear']
                as $nullable
            ) {
                $info[$nullable] = $info[$nullable] == '' ? null : $info[$nullable];
            }
            foreach (['LogChecksum', 'HasCue', 'HasLog', 'HasLogDB', 'Remastered', 'Scene']
                as $zerotruth
            ) {
                $info[$zerotruth] = !($info[$zerotruth] == '0');
            }
            foreach (['BadFiles', 'BadFolders', 'BadTags', 'CassetteApproved', 'LossymasterApproved', 'LossywebApproved', 'MissingLineage']
                as $emptytruth
            ) {
                $info[$emptytruth] = !($info[$emptytruth] == '');
            }

            $info['ripLogIds'] = empty($info['ripLogIds']) ? [] : array_map('intval', explode(',', $info['ripLogIds']));
            $info['LogCount'] = count($info['ripLogIds']);
            $info['FileList'] = explode("\n", $info['FileList']);

            $this->cache->cache_value($key, $info, ($info['Seeders'] ?? 0) > 0 ? 600 : 3600);
        }

        if ($this->viewerId) {
            $info['PersonalFL'] = $info['FreeTorrent'] == '0' && $this->hasToken($this->viewerId);
            $info['IsSnatched'] = $this->showSnatched && $this->isSnatched($this->viewerId);
        } else {
            $info['PersonalFL'] = false;
            $info['IsSnatched'] = false;
        }

        return $info;
    }

    /**
     * Group ID this torrent belongs to
     *
     * @return int group id
     */
    public function groupId(): int {
        return $this->info()['GroupID'];
    }

    /**
     * Get the torrent group in which this torrent belongs.
     *
     * @return TGroup group instance
     */
    public function group(): TGroup {
        return new TGroup($this->info()['GroupID']);
    }

    /**
     * The uploader of this torrent
     *
     * @return User uploader
     */
    public function uploader(): User {
        return new User($this->info()['UserID']);
    }

    /**
     * The infohash of this torrent
     *
     * @return string hexified infohash
     */
    public function infohash(): string {
        return $this->info()['info_hash'];
    }

    /**
     * Is this a remastered release?
     *
     * @return bool remastered
     */
    public function isRemastered(): bool {
        return $this->info()['Remastered'];
    }

    public function isPerfectFlac(): bool {
        return (bool)$this->db->scalar("
            SELECT 1
            FROM torrents t
            WHERE t.Format = 'FLAC'
                AND (
                    (t.Media = 'CD' AND t.LogScore = 100)
                    OR (t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT'))
                )
                AND ID = ?
            ", $this->id
        );
    }

    public function isPerfecterFlac(): bool {
        return (bool)$this->db->scalar("
            SELECT 1
            FROM torrents t
            WHERE t.Format = 'FLAC'
                AND (
                    (t.Media = 'CD' AND t.LogScore = 100)
                    OR t.Media IN ('Cassette', 'DAT')
                    OR (t.Media IN ('Vinyl', 'DVD', 'Soundboard', 'SACD', 'BD') AND t.Encoding = '24bit Lossless')
                )
                AND ID = ?
            ", $this->id
        );
    }

    /**
     * Combine torrent media into a standardized file name
     *
     * @param array Torrent metadata
     * @param bool whether to use .txt or .torrent as file extension
     * @param int $MaxLength maximum file name length
     * @return string file name with at most $MaxLength characters
     */
    public function torrentFilename(bool $asText, int $MaxLength) {
        $MaxLength -= strlen($this->id) + 1 + ($asText ? 4 : 8);
        $info = $this->info();
        $group = $this->group();
        $artist = safeFilename($group->artistName());
        if ($info['Year'] > 0) {
            $artist .= ".{$info['Year']}";
        }
        $meta = [];
        if ($info['Media'] != '') {
            $meta[] = $info['Media'];
        }
        if ($info['Format'] != '') {
            $meta[] = $info['Format'];
        }
        if ($info['Encoding'] != '') {
            $meta[] = $info['Encoding'];
        }
        $label = empty($meta) ? '' : ('.(' . safeFilename(implode('-', $meta)) . ')');

        $filename = safeFilename($group->name());
        if (!$filename) {
            $filename = 'Unnamed';
        } elseif (mb_strlen("$artist.$filename$label", 'UTF-8') <= $MaxLength) {
            $filename = "$artist.$filename";
        }

        $filename = shortenString($filename . $label, $MaxLength, true, false) . "-" . $this->id;
        return $asText ? "$filename.txt" : "$filename.torrent";
    }

    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
     *
     * @param mixed $TorrentData bencoded torrent without announce URL
     * @param string $AnnounceURL
     * @param int $TorrentID
     * @return string bencoded string
     */
    public function torrentBody(string $announceUrl): string {
        $filer = new \Gazelle\File\Torrent;
        $contents = $filer->get($this->id);
        if (is_null($contents)) {
            return '';
        }
        $tor = new \OrpheusNET\BencodeTorrent\BencodeTorrent;
        $tor->decodeString($contents);
        $tor->cleanDataDictionary();
        $tor->setValue([
            'announce' => $announceUrl,
            'comment'  => SITE_URL . "/torrents.php?torrentid=" . $this->id,
        ]);
        return $tor->getEncode();
    }

    public function modifyLogscore(): int {
        $count = $this->db->scalar("
            SELECT count(*) FROM torrents_logs WHERE TorrentID = ?
            ", $this->id
        );
        if (!$count) {
            $this->db->prepared_query("
                UPDATE torrents SET
                    HasLogDB = '0',
                    LogChecksum = '1',
                    LogScore = 0
                WHERE ID = ?
                ", $this->id
            );
        } else {
            $this->db->prepared_query("
                UPDATE torrents AS t
                LEFT JOIN (
                    SELECT TorrentID,
                        min(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
                        min(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
                    FROM torrents_logs
                    WHERE TorrentID = ?
                    GROUP BY TorrentID
                ) AS tl ON (t.ID = tl.TorrentID)
                SET
                    t.LogScore    = tl.Score,
                    t.LogChecksum = tl.Checksum
                WHERE t.ID = ?
                ", $this->id, $this->id
            );
        }
        $this->cache->deleteMulti(["torrent_group_" . $this->groupId(), "torrents_details_" . $this->groupId()]);
        return $this->db->affected_rows();
    }

    public function adjustLogscore(int $logId, $adjusted, int $adjScore, $adjChecksum, int $adjBy, $adjReason, array $adjDetails): int {
        $this->db->prepared_query("
            UPDATE torrents_logs SET
                Adjusted = ?, AdjustedScore = ?, AdjustedChecksum = ?, AdjustedBy = ?, AdjustmentReason = ?, AdjustmentDetails = ?
            WHERE TorrentID = ? AND LogID = ?
            ", $adjusted, $adjScore, $adjChecksum, $adjBy, $adjReason, serialize($adjDetails),
                $this->id, $logId
        );
        if ($this->db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function clearLog(int $logId): int {
        $this->db->prepared_query("
            DELETE FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $this->id, $logId
        );
        if ($this->db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function rescoreLog(int $logId, \Gazelle\Logfile $logfile, string $version): int {
        $this->db->prepared_query("
            UPDATE torrents_logs SET
                Score = ?, `Checksum` = ?, ChecksumState = ?, Ripper = ?, RipperVersion = ?,
                `Language` = ?, Details = ?, LogcheckerVersion = ?,
                Adjusted = '0'
            WHERE TorrentID = ? AND LogID = ?
            ", $logfile->score(), $logfile->checksumStatus(), $logfile->checksumState(), $logfile->ripper(), $logfile->ripperVersion(),
                $logfile->language(), $logfile->detailsAsString(), $version,
                $this->id, $logId
        );
        if ($this->db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function logfileList(): array {
        $this->db->prepared_query('
            SELECT LogID AS id,
                Score,
                `Checksum`,
                Adjusted,
                AdjustedBy,
                AdjustedScore,
                AdjustedChecksum,
                AdjustmentReason,
                AdjustmentDetails,
                Details
            FROM torrents_logs
            WHERE TorrentID = ?
            ', $this->id
        );
        $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
        $ripFiler = new \Gazelle\File\RipLog;
        $htmlFiler = new \Gazelle\File\RipLogHTML;
        foreach ($list as &$log) {
            $log['has_riplog'] = $ripFiler->exists([$this->id, $log['id']]);
            $log['html_log'] = $htmlFiler->get([$this->id, $log['id']]);
            $log['adjustment_details'] = unserialize($log['AdjustmentDetails']);
            $log['adjusted'] = ($log['Adjusted'] === '1');
            $log['adjusted_checksum'] = ($log['AdjustedChecksum'] === '1');
            $log['checksum'] = ($log['Checksum'] === '1');
            $log['details'] = empty($log['Details']) ? [] : explode("\r\n", trim($log['Details']));
            if ($log['adjusted'] && $log['checksum'] !== $log['adjustedChecksum']) {
                $log['details'][] = 'Bad/No Checksum(s)';
            }
        }
        return $list;
    }

    /**
     * Has the viewing user snatched this torrent? (And do they want
     * to know about it?)
     *
     * @param int user id
     * @return bool viewer has snatched.
     */
    public function isSnatched(int $userId): bool {
        $buckets = 64;
        $bucketMask = $buckets - 1;
        $bucketId = $this->id & $bucketMask;

        $snatchKey = "users_snatched_" . $userId . "_time";
        if (!$this->snatchBucket) {
            $this->snatchBucket = array_fill(0, $buckets, false);
            $this->updateTime = $this->cache->get_value($snatchKey);
            if ($this->updateTime === false) {
                $this->updateTime = [
                    'last' => 0,
                    'next' => 0
                ];
            }
        } elseif (isset($this->snatchBucket[$bucketId][$this->id])) {
            return true;
        }

        // Torrent was not found in the previously inspected snatch lists
        $bucket =& $this->snatchBucket[$bucketId];
        if ($bucket === false) {
            $now = time();
            // This bucket hasn't been checked before
            $bucket = $this->cache->get_value($snatchKey, true);
            if ($bucket === false || $now > $this->updateTime['next']) {
                $bucketKeyStem = 'users_snatched_' . $userId . '_';
                $updated = [];
                $qid = $this->db->get_query_id();
                if ($bucket === false || $this->updateTime['last'] == 0) {
                    for ($i = 0; $i < $buckets; $i++) {
                        $this->snatchBucket[$i] = [];
                    }
                    // Not found in cache. Since we don't have a suitable index, it's faster to update everything
                    $this->db->prepared_query("
                        SELECT fid FROM xbt_snatched WHERE uid = ?
                        ", $userId
                    );
                    while ([$id] = $this->db->next_record(MYSQLI_NUM, false)) {
                        $this->snatchBucket[$id & $bucketMask][(int)$id] = true;
                    }
                    $updated = array_fill(0, $buckets, true);
                } elseif (isset($bucket[$this->id])) {
                    // Old cache, but torrent is snatched, so no need to update
                    return true;
                } else {
                    // Old cache, check if torrent has been snatched recently
                    $this->db->prepared_query("
                        SELECT fid FROM xbt_snatched WHERE uid = ? AND tstamp >= ?
                        ", $userId, $this->updateTime['last']
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
        return isset($bucket[$this->id]);
    }

    /**
     * Remove a torrent.
     *
     * @param int userid Who is removing the torrent
     * @param string reason Why is this being deleted? (For the log)
     * @param string trackerReason The deletion reason for ocelot to report to users.
     */
    public function remove(int $userId, string $reason, int $trackerReason = -1): array {
        $qid = $this->db->get_query_id();
        $info = $this->info();
        if ($this->id > MAX_PREV_TORRENT_ID) {
            (new \Gazelle\Bonus)->removePointsForUpload($info['UserID'],
                [$info['Format'], $info['Media'], $info['Encoding'], $info['HasLogDB'], $info['LogScore'], $info['LogChecksum']]);
        }

        $manager = new \Gazelle\DB;
        $manager->relaxConstraints(true);
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents_leech_stats', [['TorrentID', $this->id]], false);
        if (!$ok) {
            return [false, $message];
        }
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents', [['ID', $this->id]]);
        if (!$ok) {
            return [false, $message];
        }
        $infohash = $this->infohash();
        $manager->relaxConstraints(false);
        (new \Gazelle\Tracker)->update_tracker('delete_torrent', [
            'id' => $this->id,
            'info_hash' => rawurlencode(hex2bin($infohash)),
            'reason' => $trackerReason,
        ]);
        $this->cache->decrement('stats_torrent_count');

        $group = $this->group();
        $groupId = $group->id();
        $Count = $this->db->scalar("
            SELECT count(*) FROM torrents WHERE GroupID = ?
            ", $groupId
        );
        if ($Count > 0) {
            (new Manager\TGroup)->refresh($groupId);
        }

        $manager->softDelete(SQLDB, 'torrents_files',                  [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_files',              [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_folders',            [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_tags',               [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_cassette_approved',      [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_lossymaster_approved',   [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_lossyweb_approved',      [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_missing_lineage',        [['TorrentID', $this->id]]);

        $this->db->prepared_query("
            INSERT INTO user_torrent_remove
                   (user_id, torrent_id)
            VALUES (?,       ?)
            ", $userId, $this->id
        );

        // Tells Sphinx that the group is removed
        $this->db->prepared_query("
            REPLACE INTO sphinx_delta
                (ID, Time)
            VALUES (?, now())
            ", $this->id
        );

        $this->db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = 'Report already dealt with (torrent deleted)'
            WHERE Status != 'Resolved'
                AND TorrentID = ?
            ", $this->id
        );
        $count = $this->db->affected_rows();
        if ($count) {
            $this->cache->decrement('num_torrent_reportsv2', $count);
        }

        // Torrent notifications
        $this->db->prepared_query("
            SELECT concat('user_notify_upload_', UserID) as ck
            FROM users_notify_torrents
            WHERE TorrentID = ?
            ", $this->id
        );
        $deleteKeys = $this->db->collect('ck', false);
        $manager->softDelete(SQLDB, 'users_notify_torrents', [['TorrentID', $this->id]]);

        if ($userId !== 0) {
            $RecentUploads = $this->cache->get_value("user_recent_up_" . $userId);
            if (is_array($RecentUploads)) {
                foreach ($RecentUploads as $Key => $Recent) {
                    if ($Recent['ID'] == $groupId) {
                        $deleteKeys[] = "user_recent_up_" . $userId;
                        break;
                    }
                }
            }
        }

        $deleteKeys[] = "torrent_download_" . $this->id;
        $deleteKeys[] = "torrent_group_" . $groupId;
        $deleteKeys[] = "torrents_details_" . $groupId;
        $this->cache->deleteMulti($deleteKeys);

        $sizeMB = number_format($this->info()['Size'] / (1024 * 1024), 2) . ' MiB';
        $username = $userId ? (new Manager\User)->findById($userId)->username() : 'system';
        (new Log)->general(
            "Torrent "
                . $this->id
                . " (" . $group->name() . ") [" . (new Manager\TorrentLabel)->load($this->info())->release()
                . "] ($sizeMB $infohash) was deleted by $username for reason: $reason"
            )
            ->torrent(
                $groupId, $this->id, $userId,
                "deleted torrent ($sizeMB $infohash) for reason: $reason"
            );

        $this->db->set_query_id($qid);
        return [true, "torrent " . $this->id . " removed"];
    }

    public function expireToken(int $userId): bool {
        $hash = $this->db->scalar("
            SELECT info_hash FROM torrents WHERE ID = ?
            ", $this->id
        );
        if (!$hash) {
            return false;
        }
        $this->db->prepared_query("
            UPDATE users_freeleeches SET
                Expired = true
            WHERE UserID = ?
                AND TorrentID = ?
            ", $userId, $this->id
        );
        $this->cache->delete_value("users_tokens_{$userId}");
        (new \Gazelle\Tracker)->update_tracker('remove_token', ['info_hash' => rawurlencode($hash), 'userid' => $userId]);
        return true;
    }

    /**
     * Get the requests filled by this torrent.
     * (Should only be one, but hey, who knows what the original developer was looking to catch?)
     * @param int torrent ID
     * @return DB object to loop over [request id, filler user id, date filled]
     */
    public function requestFills(): array {
        $this->db->prepared_query("
            SELECT r.ID, r.FillerID, r.TimeFilled FROM requests AS r WHERE r.TorrentID = ?
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function peerlistTotal() {
        $key = sprintf(self::CACHE_KEY_PEERLIST_TOTAL, $this->id);
        if (($total = $this->cache->get_value($key)) === false) {
            // force flush the first page of results
            $this->cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, 0));
            $total = $this->db->scalar("
                SELECT count(*)
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ", $this->id
            );
            $this->cache->cache_value($key, $total, 300);
        }
        return $total;
    }

    public function peerlistPage(int $userId, int $limit, int $offset) {
        $key = sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, $offset);
        $list = $this->cache->get_value($key);
        if ($list === false) {
            // force flush the next page of results
            $this->cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, $offset + $limit));
            $this->db->prepared_query("
                SELECT
                    xfu.active,
                    xfu.connectable,
                    xfu.remaining,
                    xfu.uploaded,
                    xfu.useragent,
                    xfu.ip           AS ipv4addr,
                    xfu.uid          AS user_id,
                    t.Size           AS size,
                    sx.name          AS seedbox,
                    EXISTS(SELECT 1 FROM users_downloads ud WHERE ud.UserID = xfu.uid AND ud.TorrentID = xfu.fid) AS is_download,
                    EXISTS(SELECT 1 FROM xbt_snatched xs WHERE xs.uid = xfu.uid AND xs.fid = xfu.fid) AS is_snatched
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                LEFT JOIN user_seedbox sx ON (xfu.ip = inet_ntoa(sx.ipaddr) AND xfu.useragent = sx.useragent AND xfu.uid = ?)
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ORDER BY xfu.uid = ? DESC, xfu.uploaded DESC
                LIMIT ? OFFSET ?
                ", $userId, $this->id, $userId, $limit, $offset
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
            $this->cache->cache_value($key, $list, 300);
        }
        return $list;
    }

    public function downloadTotal(): int {
        return $this->db->scalar("
            SELECT count(*) FROM users_downloads WHERE TorrentID = ?
            ", $this->id
        );
    }

    public function downloadPage(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT ud.UserID AS user_id,
                ud.Time      AS timestamp,
                EXISTS(SELECT 1 FROM xbt_snatched xs WHERE xs.uid = ud.UserID AND xs.fid = ud.TorrentID) AS is_snatched,
                EXISTS(SELECT 1 FROM xbt_files_users xfu WHERE xfu.uid = ud.UserID AND xfu.fid = ud.TorrentID) AS is_seeding
            FROM users_downloads ud
            WHERE ud.TorrentID = ?
            ORDER BY ud.Time DESC, ud.UserID
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function snatchTotal(): int {
        return $this->db->scalar("
            SELECT count(*) FROM xbt_snatched WHERE fid = ?
            ", $this->id
        );
    }

    public function snatchPage(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT xs.uid AS user_id,
                from_unixtime(xs.tstamp) AS timestamp,
                EXISTS(SELECT 1 FROM users_downloads ud WHERE ud.UserID = xs.uid AND ud.TorrentID = xs.fid) AS is_download,
                EXISTS(SELECT 1 FROM xbt_files_users xfu WHERE xfu.uid = xs.uid AND xfu.fid = xs.fid) AS is_seeding
            FROM xbt_snatched xs
            WHERE xs.fid = ?
            ORDER BY xs.tstamp DESC
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
