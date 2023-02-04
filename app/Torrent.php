<?php

namespace Gazelle;

class Torrent extends TorrentAbstract {
    final const CACHE_LOCK               = 'torrent_lock_%d';
    final const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    final const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    final const CACHE_REPORTLIST         = 't_rpt2_%s_%d';
    final const USER_RECENT_UPLOAD       = 'u_recent_up_%d';

    final const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists

    protected $tokenCache;
    protected $updateTime;

    public function location(): string { return "torrents.php?id={$this->groupId()}&torrentid={$this->id}#torrent{$this->id}"; }
    public function tableName(): string { return 'torrents'; }

    public function infoRow(): ?array {
        return self::$db->rowAssoc("
            SELECT t.GroupID,
                t.UserID,
                t.Media,
                t.Format,
                t.Encoding,
                t.Remastered,
                t.RemasterYear,
                t.RemasterTitle,
                t.RemasterCatalogueNumber,
                t.RemasterRecordLabel,
                t.Scene,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                hex(t.info_hash) AS info_hash,
                t.info_hash      AS info_hash_raw,
                t.FileCount,
                t.FileList,
                t.FilePath,
                t.Size,
                t.FreeTorrent,
                t.FreeLeechType,
                t.Time,
                t.Description,
                t.LastReseedRequest,
                tls.Seeders,
                tls.Leechers,
                tls.Snatched,
                tls.last_action,
                tbt.TorrentID          AS BadTags,
                tbf.TorrentID          AS BadFolders,
                tfi.TorrentID          AS BadFiles,
                mli.TorrentID          AS MissingLineage,
                cas.TorrentID          AS CassetteApproved,
                lma.TorrentID          AS LossymasterApproved,
                lwa.TorrentID          AS LossywebApproved,
                group_concat(tl.LogID) AS ripLogIds
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrents_bad_tags             AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN torrents_bad_folders          AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN torrents_bad_files            AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN torrents_missing_lineage      AS mli ON (mli.TorrentID = t.ID)
            LEFT JOIN torrents_cassette_approved    AS cas ON (cas.TorrentID = t.ID)
            LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN torrents_lossyweb_approved    AS lwa ON (lwa.TorrentID = t.ID)
            LEFT JOIN torrents_logs                 AS tl  ON (tl.TorrentID  = t.ID)
            WHERE t.ID = ?
            GROUP BY t.ID
            ", $this->id
        );
    }

    public function hasReport(\Gazelle\User $viewer): bool {
        return count($this->reportIdList($viewer)) > 0;
    }

    /**
     * Check if the viewer has an active freeleech token on this torrent
     */
    public function hasToken(int $userId): bool {
        if (!$this->tokenCache) {
            $key = "users_tokens_" . $userId;
            $this->tokenCache = self::$cache->get_value($key);
            if ($this->tokenCache === false) {
                $qid = self::$db->get_query_id();
                self::$db->prepared_query("
                    SELECT TorrentID FROM users_freeleeches WHERE Expired = 0 AND UserID = ?
                    ", $userId
                );
                $this->tokenCache = array_fill_keys(self::$db->collect('TorrentID', false), true);
                self::$db->set_query_id($qid);
                self::$cache->cache_value($key, $this->tokenCache, 3600);
            }
        }
        return isset($this->tokenCache[$this->id]);
    }

    public function isUploadLocked(): bool {
        return (bool)self::$cache->get_value(sprintf(self::CACHE_LOCK, $this->id));
    }

    public function lockUpload(): void {
        self::$cache->cache_value(sprintf(self::CACHE_LOCK, $this->id), true, 120);
    }

    public function unlockUpload(): void {
        self::$cache->delete_value(sprintf(self::CACHE_LOCK, $this->id));
    }

    /**
     * Get the reports associated with this torrent
     * Non-admin users do not see Edited reports
     *
     * @return array of ids of \Gazelle\Torrent\Report
     */
    public function reportIdList(\Gazelle\User $viewer): array {
        $key = sprintf(self::CACHE_REPORTLIST, $viewer->permitted('admin_reports') ? 'a' : 'u', $this->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $qid = self::$db->get_query_id();
            if ($viewer->permitted('admin_reports')) {
                self::$db->prepared_query("
                    SELECT ID
                    FROM reportsv2
                    WHERE Status != 'Resolved'
                        AND TorrentID = ?
                    ", $this->id
                );
            } else {
                self::$db->prepared_query("
                    SELECT ID
                    FROM reportsv2
                    WHERE Status != 'Resolved'
                        AND Type != 'edited'
                        AND TorrentID = ?
                    ", $this->id
                );
            }
            $list = self::$db->collect(0, false);
            self::$db->set_query_id($qid);
            self::$cache->cache_value($key, $list, 7200);
        }
        return $list;
    }

    /**
     * Combine torrent media into a standardized file name
     */
    public function torrentFilename(bool $asText, int $maxLength): string {
        $tgroup = $this->group();
        $filename = implode('.',
            match($tgroup->categoryName()) {
                'Music'  => [
                    $tgroup->artistRole()->text(), $tgroup->year(), $tgroup->name(),
                    '(' . implode('-', [$this->media(), $this->format(), $this->encoding()]) . ')'
                ],
                'Audiobooks',
                'Comedy' => [$tgroup->year(), $tgroup->name()],
                default  => [$tgroup->name()],
            }
        );
        $maxLength -= strlen($this->id) + 1 + ($asText ? 4 : 8);
        $filename = safeFilename(shortenString($filename, $maxLength, true, false))
            . "-" . $this->id;
        return $asText ? "$filename.txt" : "$filename.torrent";
    }

    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
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

    public function addLogDb(Logfile $logfile, string $version): int {
        self::$db->prepared_query('
            INSERT INTO torrents_logs
                   (TorrentID, Score, `Checksum`, `FileName`, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Details)
            VALUES (?,         ?,      ?,          ?,         ?,      ?,              ?,         ?,             ?,                 ?)
            ', $this->id, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(), $logfile->ripper(),
                $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
                \OrpheusNET\Logchecker\Logchecker::getLogcheckerVersion(), $logfile->detailsAsString()
        );
        return self::$db->inserted_id();
    }

    public function adjustLogscore(int $logId, bool $adjusted, int $adjScore, bool $adjChecksum, int $adjBy, $adjReason, array $adjDetails): int {
        self::$db->prepared_query("
            UPDATE torrents_logs SET
                Adjusted = ?, AdjustedScore = ?, AdjustedChecksum = ?, AdjustedBy = ?, AdjustmentReason = ?, AdjustmentDetails = ?
            WHERE TorrentID = ? AND LogID = ?
            ", $adjusted ? '1' : '0', $adjScore, $adjChecksum ? '1' : '0', $adjBy, $adjReason, serialize($adjDetails),
                $this->id, $logId
        );
        if (self::$db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function clearLog(int $logId): int {
        self::$db->prepared_query("
            DELETE FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $this->id, $logId
        );
        if (self::$db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function logfileList(\Gazelle\File\RipLog $ripFiler, \Gazelle\File\RipLogHTML $htmlFiler): array {
        self::$db->prepared_query("
            SELECT LogID AS id,
                Score,
                `Checksum`,
                Adjusted,
                AdjustedBy,
                AdjustedScore,
                AdjustedChecksum,
                AdjustmentReason,
                coalesce(AdjustmentDetails, 'a:0:{}') AS AdjustmentDetails,
                Details
            FROM torrents_logs
            WHERE TorrentID = ?
            ", $this->id
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$log) {
            $log['has_riplog'] = $ripFiler->exists([$this->id, $log['id']]);
            $log['html_log'] = $htmlFiler->get([$this->id, $log['id']]);
            $log['adjustment_details'] = unserialize($log['AdjustmentDetails']);
            $log['adjusted'] = ($log['Adjusted'] === '1');
            $log['adjusted_checksum'] = ($log['AdjustedChecksum'] === '1');
            $log['checksum'] = ($log['Checksum'] === '1');
            $log['details'] = empty($log['Details']) ? [] : explode("\r\n", trim($log['Details']));
            if ($log['adjusted'] && $log['checksum'] !== $log['adjusted_checksum']) {
                $log['details'][] = 'Bad/No Checksum(s)';
            }
        }
        return $list;
    }

    public function modifyLogscore(): int {
        $count = self::$db->scalar("
            SELECT count(*) FROM torrents_logs WHERE TorrentID = ?
            ", $this->id
        );
        if (!$count) {
            self::$db->prepared_query("
                UPDATE torrents SET
                    HasLogDB = '0',
                    LogChecksum = '1',
                    LogScore = 0
                WHERE ID = ?
                ", $this->id
            );
        } else {
            self::$db->prepared_query("
                UPDATE torrents AS t
                LEFT JOIN (
                    SELECT TorrentID,
                        min(CASE WHEN Adjusted = '1' OR AdjustedScore != Score THEN AdjustedScore ELSE Score END) AS Score,
                        min(CASE WHEN Adjusted = '1' OR AdjustedChecksum != Checksum THEN AdjustedChecksum ELSE Checksum END) AS Checksum
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
        $this->flush();
        return self::$db->affected_rows();
    }

    public function rescoreLog(int $logId, \Gazelle\Logfile $logfile, string $version): int {
        self::$db->prepared_query("
            UPDATE torrents_logs SET
                Score = ?, `Checksum` = ?, ChecksumState = ?, Ripper = ?, RipperVersion = ?,
                `Language` = ?, Details = ?, LogcheckerVersion = ?,
                Adjusted = '0'
            WHERE TorrentID = ? AND LogID = ?
            ", $logfile->score(), $logfile->checksumStatus(), $logfile->checksumState(), $logfile->ripper(), $logfile->ripperVersion(),
                $logfile->language(), $logfile->detailsAsString(), $version,
                $this->id, $logId
        );
        if (self::$db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    public function updateLogScore(LogfileSummary $summary): int {
        self::$db->prepared_query("
            UPDATE torrents SET
                HasLogDB = '1',
                LogScore = ?,
                LogChecksum = ?
            WHERE ID = ?
            ", $summary->overallScore(), $summary->checksumStatus(),
                $this->id
        );
        $this->flush();
        self::$cache->delete_value(sprintf(TGroup::CACHE_TLIST_KEY, $this->groupId()));
        return self::$db->affected_rows();
    }

    /**
     * Remove all logfiles attached to this upload
     *
     * @return int number of logfiles removed
     */
    public function removeAllLogs(User $user, File\RipLog $ripLog, File\RipLogHTML $ripLogHtml, Log $logger): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM torrents_logs WHERE TorrentID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE torrents SET
                HasLog      = '1',
                HasLogDB    = '0',
                LogChecksum = '0',
                LogScore    = 0
            WHERE ID = ?
            ", $this->id
        );
        $logger->torrent($this->groupId(), $this->id, $user->id(), "All logs removed from torrent");
        self::$db->commit();
        $this->flush();

        $ripLog->remove([$this->id, null]);
        $ripLogHtml->remove([$this->id, null]);

        return $affected;
    }

    public function removeLogDb(): int {
        self::$db->prepared_query('
            DELETE FROM torrents_logs WHERE TorrentID = ?
            ', $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Issue a reseed request (via PM) to the uploader and 100
     * most recent enabled snatchers
     *
     * @return int number of people messaged
     */
    public function issueReseedRequest(User $viewer): int {
        self::$db->prepared_query('
            UPDATE torrents SET
                LastReseedRequest = now()
            WHERE ID = ?
            ', $this->id
        );

        self::$db->prepared_query("
            SELECT s.uid      AS id,
                'snatched'    AS action,
                from_unixtime(max(s.tstamp)) AS tdate
            FROM xbt_snatched AS s
            INNER JOIN users_main AS u ON (s.uid = u.ID)
            WHERE s.fid = ?
                AND u.Enabled = ?
            GROUP BY s.uid
            ORDER BY s.tstamp DESC
            LIMIT 100
            ", $this->id, '1'
        );
        $notify = self::$db->to_array('id', MYSQLI_ASSOC, false);
        $notify[$this->uploaderId()] = [
            'action' => 'uploaded',
            'tdate'  => $this->created(),
        ];

        $userMan   = new Manager\User;
        $groupId   = $this->groupId();
        $name      = $this->group()->text();
        $torrentId = $this->id;

        foreach ($notify as $userId => $info) {
            $userMan->sendPM($userId, 0,
                "Re-seed request for torrent $name",
                self::$twig->render('torrent/reseed-pm.twig', [
                    'action'     => $info['action'],
                    'date'       => $info['tdate'],
                    'group_id'   => $groupId,
                    'torrent_id' => $torrentId,
                    'name'       => $name,
                    'user'       => new User($userId),
                    'viewer'     => $viewer,
                ])
            );
        }
        return count($notify);
    }

    public function hasFlag(TorrentFlag $flag): bool {
        return (bool)self::$db->scalar("
            SELECT 1 FROM {$flag->value} WHERE TorrentID = ?
            ", $this->id
        );
    }

    public function addFlag(TorrentFlag $flag, User $user): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO {$flag->value} (TorrentID, UserID) VALUES (?, ?)
            ", $this->id, $user->id()
        );
        return self::$db->affected_rows();
    }

    public function removeFlag(TorrentFlag $flag): int {
        self::$db->prepared_query("
            DELETE FROM {$flag->value} WHERE TorrentID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Remove a torrent.
     */
    public function remove(int $userId, string $reason, int $trackerReason = -1): array {
        $qid = self::$db->get_query_id();
        $this->info();
        if ($this->id > MAX_PREV_TORRENT_ID) {
            (new \Gazelle\User\Bonus($this->uploader()))->removePointsForUpload($this);
        }

        $manager = new \Gazelle\DB;
        $manager->relaxConstraints(true);
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents_leech_stats', [['TorrentID', $this->id]], false);
        if (!$ok) {
            return [false, $message];
        }
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents', [['ID', $this->id]]);
        $infohash = $this->infohash();
        $manager->relaxConstraints(false);
        (new \Gazelle\Tracker)->update_tracker('delete_torrent', [
            'id' => $this->id,
            'info_hash' => rawurlencode(hex2bin($infohash)),
            'reason' => $trackerReason,
        ]);
        self::$cache->decrement('stats_torrent_count');

        $manager->softDelete(SQLDB, 'torrents_files',                  [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_files',              [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_folders',            [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_bad_tags',               [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_cassette_approved',      [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_lossymaster_approved',   [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_lossyweb_approved',      [['TorrentID', $this->id]]);
        $manager->softDelete(SQLDB, 'torrents_missing_lineage',        [['TorrentID', $this->id]]);

        self::$db->prepared_query("
            INSERT INTO user_torrent_remove
                   (user_id, torrent_id)
            VALUES (?,       ?)
            ", $userId, $this->id
        );

        // Tells Sphinx that the group is removed
        self::$db->prepared_query("
            REPLACE INTO sphinx_delta
                (ID, Time)
            VALUES (?, now())
            ", $this->id
        );

        self::$db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = 'Report already dealt with (torrent deleted)'
            WHERE Status != 'Resolved'
                AND TorrentID = ?
            ", $this->id
        );
        $count = self::$db->affected_rows();
        if ($count) {
            self::$cache->decrement('num_torrent_reportsv2', $count);
        }

        // Torrent notifications
        self::$db->prepared_query("
            SELECT concat('user_notify_upload_', UserID) as ck
            FROM users_notify_torrents
            WHERE TorrentID = ?
            ", $this->id
        );
        $deleteKeys = self::$db->collect('ck', false);
        $manager->softDelete(SQLDB, 'users_notify_torrents', [['TorrentID', $this->id]]);

        $groupId = $this->group()->id();
        if ($userId !== 0) {
            $key = sprintf(self::USER_RECENT_UPLOAD, $userId);
            $recent = self::$cache->get_value($key);
            if (is_array($recent) && in_array($groupId, $recent)) {
                $deleteKeys[] = $key;
            }
        }

        array_push($deleteKeys, "zz_t_" . $this->id, sprintf(self::CACHE_KEY, $this->id),
            "torrent_download_" . $this->id, "torrent_group_" . $groupId, "torrents_details_" . $groupId
        );
        self::$cache->delete_multi($deleteKeys);
        $this->group()->refresh();

        $sizeMB = number_format($this->info()['Size'] / (1024 * 1024), 2) . ' MiB';
        $username = $userId ? (new Manager\User)->findById($userId)->username() : 'system';
        (new Log)->general(
            "Torrent "
                . $this->id . " (" . $this->name() . ") [" . $this->edition() .
                "] ($sizeMB $infohash) was deleted by $username for reason: $reason"
            )
            ->torrent(
                $groupId, $this->id, $userId,
                "deleted torrent ($sizeMB $infohash) for reason: $reason"
            );

        self::$db->set_query_id($qid);
        return [true, "torrent " . $this->id . " removed"];
    }

    public function expireToken(int $userId): bool {
        $hash = self::$db->scalar("
            SELECT info_hash FROM torrents WHERE ID = ?
            ", $this->id
        );
        if (!$hash) {
            return false;
        }
        self::$db->prepared_query("
            UPDATE users_freeleeches SET
                Expired = true
            WHERE UserID = ?
                AND TorrentID = ?
            ", $userId, $this->id
        );
        self::$cache->delete_value("users_tokens_{$userId}");
        (new \Gazelle\Tracker)->update_tracker('remove_token', ['info_hash' => rawurlencode($hash), 'userid' => $userId]);
        return true;
    }

    /**
     * Get the requests filled by this torrent.
     */
    public function requestFills(): array {
        self::$db->prepared_query("
            SELECT r.ID, r.FillerID, r.TimeFilled FROM requests AS r WHERE r.TorrentID = ?
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_NUM, false);
    }

    public function seederList(int $userId, int $limit, int $offset) {
        $key = sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, $offset);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            // force flush the next page of results
            self::$cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, $offset + $limit));
            self::$db->prepared_query("
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
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 300);
        }
        return $list;
    }

    public function downloadTotal(): int {
        return self::$db->scalar("
            SELECT count(DISTINCT UserID) FROM users_downloads WHERE TorrentID = ?
            ", $this->id
        );
    }

    public function downloadList(int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT ud.UserID AS user_id,
                min(ud.Time) AS timestamp,
                count(*)     AS total,
                EXISTS(SELECT 1 FROM xbt_snatched xs WHERE xs.uid = ud.UserID AND xs.fid = ud.TorrentID) AS is_snatched,
                EXISTS(SELECT 1 FROM xbt_files_users xfu WHERE xfu.uid = ud.UserID AND xfu.fid = ud.TorrentID) AS is_seeding
            FROM users_downloads ud
            WHERE ud.TorrentID = ?
            GROUP BY user_id, is_snatched, is_seeding
            ORDER BY ud.Time DESC, ud.UserID
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function snatchList(int $limit, int $offset): array {
        self::$db->prepared_query("
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
