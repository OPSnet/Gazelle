<?php

namespace Gazelle;

class Torrent extends TorrentAbstract {
    final public const tableName               = 'torrents';
    final public const CACHE_KEY               = 't_%d';
    final public const CACHE_FOLDERNAME        = 'foldername_%s';
    final public const CACHE_KEY_PEERLIST_PAGE = 'peerlist_page_%d_%d';
    final public const USER_RECENT_UPLOAD      = 'u_recent_up_%d';

    final protected const ObjectName   = 'torrent';
    final protected const PkColumn     = 'ID';
    final protected const JoinColumn   = 'TorrentAttrID';
    final protected const ObjectColumn = 'TorrentID';

    public function location(): string {
        return "torrents.php?id={$this->groupId()}&torrentid={$this->id}#torrent{$this->id}";
    }

    public function infoRow(): ?array {
        $info = self::$db->rowAssoc("
            SELECT t.GroupID,
                t.UserID,
                t.Media,
                t.Format,
                t.Encoding,
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
                t.info_hash,
                t.FileCount,
                t.FilePath,
                t.Size,
                t.FreeTorrent,
                t.FreeLeechType,
                t.created,
                t.Description,
                t.LastReseedRequest,
                tls.Seeders,
                tls.Leechers,
                tls.Snatched,
                tls.last_action,
                group_concat(tl.LogID) AS ripLogIds
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrents_logs      AS tl  ON (tl.TorrentID  = t.ID)
            WHERE t.ID = ?
            GROUP BY t.ID
            ", $this->id
        );
        if ($info) {
            self::$db->prepared_query("
                SELECT a.Name
                FROM torrent_attr a JOIN torrent_has_attr ha ON (a.ID = ha.TorrentAttrID)
                WHERE ha.TorrentID = ?
            ", $this->id);
            $info['attr'] = [];
            foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $row) {
                $info['attr'][$row['Name']] = true;
            }
        }
        return $info;
    }

    /**
     * Combine torrent media into a standardized file name
     */
    public function torrentFilename(bool $asText, int $maxLength): string {
        $tgroup = $this->group();
        $filename = implode('.',
            match ($tgroup->categoryName()) {
                'Music'  => [
                    $tgroup->artistRole()->text(), $tgroup->year(), $tgroup->name(),
                    '(' . implode('-', [$this->media(), $this->format(), $this->encoding()]) . ')'
                ],
                'Audiobooks',
                'Comedy' => [$tgroup->year(), $tgroup->name()],
                default  => [$tgroup->name()],
            }
        );
        $maxLength -= strlen((string)$this->id) + 1 + ($asText ? 4 : 8);
        $filename = safeFilename(shortenString($filename, $maxLength, true, false))
            . "-" . $this->id;
        return $asText ? "$filename.txt" : "$filename.torrent";
    }

    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
     */
    public function torrentBody(string $announceUrl): string {
        $contents = new File\Torrent($this->id)->get();
        if ($contents == false) {
            return '';
        }
        $tor = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
        try {
            $tor->decodeString($contents);
        } catch (\RuntimeException) {
            return '';
        }
        $tor->cleanDataDictionary();
        $tor->setValue([
            'announce' => $announceUrl,
            'comment'  => SITE_URL . "/torrents.php?torrentid=" . $this->id,
        ]);
        return $tor->getEncode();
    }

    public function adjustLogscore(
        int    $logId,
        bool   $adjusted,
        int    $adjScore,
        bool   $adjChecksum,
        string $adjReason,
        array  $adjDetails,
        User   $adjBy,
    ): int {
        self::$db->prepared_query("
            UPDATE torrents_logs SET
                Adjusted = ?, AdjustedScore = ?, AdjustedChecksum = ?, AdjustedBy = ?, AdjustmentReason = ?, AdjustmentDetails = ?
            WHERE TorrentID = ? AND LogID = ?
            ", $adjusted ? '1' : '0', $adjScore, $adjChecksum ? '1' : '0', $adjBy->id, $adjReason, serialize($adjDetails),
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

    public function modifyLogscore(): int {
        $count = self::$db->scalar("
            SELECT count(*) FROM torrents_logs WHERE TorrentID = ?
            ", $this->id
        );
        if (!$count) {
            self::$db->prepared_query("
                UPDATE torrents SET
                    HasLogDB    = '0',
                    LogChecksum = '0',
                    LogScore    = 0
                WHERE ID = ?
                ", $this->id
            );
        } else {
            self::$db->prepared_query("
                UPDATE torrents AS t
                LEFT JOIN (
                    SELECT TorrentID,
                        min(CASE WHEN Adjusted = '1' AND AdjustedScore    != Score    THEN AdjustedScore    ELSE Score    END) AS Score,
                        min(CASE WHEN Adjusted = '1' AND AdjustedChecksum != Checksum THEN AdjustedChecksum ELSE Checksum END) AS Checksum
                    FROM torrents_logs
                    WHERE TorrentID = ?
                    GROUP BY TorrentID
                ) AS tl ON (t.ID = tl.TorrentID)
                SET
                    t.HasLogDB    = '1',
                    t.LogScore    = tl.Score,
                    t.LogChecksum = tl.Checksum
                WHERE t.ID = ?
                ", $this->id, $this->id
            );
        }
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function rescoreLog(int $logId, Logfile $logfile, string $version, bool $modifyLogscore = true): int {
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
        if ($modifyLogscore && self::$db->affected_rows() > 0) {
            return $this->modifyLogscore();
        }
        return 0;
    }

    /**
     * Remove all logfiles attached to this upload
     *
     * @return int number of logfiles removed
     */
    public function removeAllLogs(User $user): int {
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
        $this->logger()->torrent($this, $user, "All logs removed from torrent");
        self::$db->commit();
        $this->flush();
        new File\RipLog($this->id, '*')->remove();
        new File\RipLogHTML($this->id, '*')->remove();

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
    public function issueReseedRequest(User $viewer, Manager\User $userMan): int {
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
        $notify = self::$db->to_array('id', MYSQLI_ASSOC);
        $notify[$this->uploaderId()] = [
            'action' => 'uploaded',
            'tdate'  => $this->created(),
        ];

        $groupId   = $this->groupId();
        $name      = $this->group()->text();
        $torrentId = $this->id;

        foreach ($notify as $userId => $info) {
            $user = $userMan->findById((int)$userId);
            if (is_null($user)) {
                continue;
            }
            $user->inbox()->createSystem(
                "Re-seed request for torrent $name",
                self::$twig->render('torrent/reseed-pm.bbcode.twig', [
                    'action'     => $info['action'],
                    'date'       => $info['tdate'],
                    'group_id'   => $groupId,
                    'torrent_id' => $torrentId,
                    'name'       => $name,
                    'user'       => $user,
                    'viewer'     => $viewer,
                ])
            );
        }
        $this->flush();
        return count($notify);
    }

    public function expireToken(User $user): int {
        $hash = (string)self::$db->scalar("
            SELECT info_hash FROM torrents WHERE ID = ?
            ", $this->id
        );
        if (!$hash) {
            return 0;
        }
        self::$db->prepared_query("
            UPDATE users_freeleeches SET
                Expired = true
            WHERE UserID = ?
                AND TorrentID = ?
            ", $user->id, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value("users_tokens_{$user->id}");
        new Tracker()->removeToken($this, $user);
        return $affected;
    }

    /**
     * Get the requests filled by this torrent.
     */
    public function requestFills(
        Manager\Request $manager = new Manager\Request()
    ): array {
        $idList = $this->pg()->columnex("
            select id_request from request where id_torrent = $1
            ", $this->id
        );
        $result = [];
        foreach ($idList as $id) {
            $request = $manager->findById($id);
            if ($request) {
                $result[] = $request;
            }
        }
        return $result;
    }

    public function downloadTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(DISTINCT UserID) FROM users_downloads WHERE TorrentID = ?
            ", $this->id
        );
    }

    public function downloadList(User $user, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT ud.UserID             AS user_id,
                min(ud.Time)             AS timestamp_min,
                max(ud.Time)             AS timestamp_max,
                seeder.uid   IS NOT NULL AS is_seeding,
                snatcher.uid IS NOT NULL AS is_snatched,
                count(*)                 AS total
            FROM users_downloads ud
            LEFT JOIN (SELECT DISTINCT uid FROM xbt_files_users  WHERE fid = ?) seeder
                ON (seeder.uid = ud.UserID)
            LEFT JOIN (SELECT DISTINCT uid FROM xbt_snatched WHERE fid = ?) snatcher
                ON (snatcher.uid = ud.UserID)
            WHERE ud.TorrentID = ?
            GROUP BY user_id, is_snatched, is_seeding
            ORDER BY user_id != ?, ud.Time DESC, ud.UserID
            LIMIT ? OFFSET ?
            ", $this->id, $this->id, $this->id, $user->id, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function seederList(User $user, int $limit, int $offset): array {
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
                    xfu.ip                        AS ipv4addr,
                    xfu.uid                       AS user_id,
                    t.Size                        AS size,
                    sx.name                       AS seedbox,
                    downloader.UserID IS NOT NULL AS is_download,
                    snatcher.uid      IS NOT NULL AS is_snatched
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                LEFT JOIN (SELECT DISTINCT UserID FROM users_downloads WHERE TorrentID = ?) downloader
                    ON (downloader.UserID = xfu.uid)
                LEFT JOIN (SELECT DISTINCT uid FROM xbt_snatched WHERE fid = ?) snatcher USING (uid)
                LEFT JOIN user_seedbox sx
                    ON (xfu.ip = inet_ntoa(sx.ipaddr)
                        AND xfu.useragent = sx.useragent
                        AND xfu.uid = ?
                )
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ORDER BY xfu.uid != ?, xfu.uploaded DESC
                LIMIT ? OFFSET ?
                ", $this->id, $this->id, $user->id, $this->id, $user->id, $limit, $offset
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value($key, $list, 300);
        }
        return $list;
    }

    public function snatchList(User $user, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT xs.uid AS user_id,
                from_unixtime(xs.tstamp)      AS timestamp,
                downloader.UserID IS NOT NULL AS is_download,
                seeder.uid        IS NOT NULL AS is_seeding
            FROM xbt_snatched xs
            LEFT JOIN (SELECT DISTINCT UserID FROM users_downloads WHERE TorrentID = ?) downloader
                ON (downloader.UserID = xs.uid)
            LEFT JOIN (SELECT DISTINCT uid FROM xbt_files_users WHERE fid = ?) seeder USING (uid)
            WHERE xs.fid = ?
            ORDER BY xs.uid != ?, xs.tstamp DESC, xs.uid
            LIMIT ? OFFSET ?
            ", $this->id, $this->id, $this->id, $user->id, $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    /**
     * Create a string that contains file info in a format that's easy to use for Sphinx
     *
     * @return string with the format .EXT sSIZEs NAME DELIMITER
     */
    public function metaFilename(string $name, int $size): string {
        $name = make_utf8(strtr($name, "\n\r\t", '   '));
        $extPos = mb_strrpos($name, '.');
        $ext = $extPos === false ? '' : trim(mb_substr($name, $extPos + 1));
        return sprintf(".%s s%ds %s %s", $ext, $size, $name, FILELIST_DELIM);
    }

    public function flushFoldernameCache(): void {
        self::$cache->delete_value(
            sprintf(self::CACHE_FOLDERNAME, signature($this->path(), FOLDER_CLASH_SALT))
        );
    }

    /**
     * Regenerate a torrent's file list from its meta data.
     *
     * @return int number of files regenned
     */
    public function regenerateFilelist(\OrpheusNET\BencodeTorrent\BencodeTorrent $encoder): int {
        $torrentFile = new File\Torrent($this->id)->get();
        if ($torrentFile === false) {
            return 0;
        }
        $encoder->decodeString($torrentFile);
        $data = $encoder->getData();
        [
            'total_size' => $totalSize,
            'files'      => $fileList
        ] = $encoder->getFileList();
        $this->setField('Size', $totalSize)
            ->setField('FilePath', isset($data['info']['files']) ? make_utf8($encoder->getName()) : '')
            ->setField('FileList', implode("\n",
                array_map(fn($f) => $this->metaFilename($f['path'], $f['size']), $fileList)
            ))
            ->modify();
        $this->flushFoldernameCache();
        return count($fileList);
    }

    /**
     * Remove a torrent. There is additional context required that prevents the
     * standard mechanism from being called directly. This method performs the
     * necessary housekeeping in the tables and then calls the
     * BaseAttrObject::remove() to do the rest.
     */
    public function removeTorrent(?User $user, string $reason, int $trackerReason = -1, bool $removePoints = true): array {
        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        $this->info();
        if ($removePoints) {
            $points = new BonusUploadReward()->reward($this);
            if ($points !== 0) {
                new User\Bonus($this->uploader())->removePoints($points);
            }
        }

        // copy the metadata that will be needed after the row has been removed
        $edition  = $this->edition();
        $tgroup   = $this->group();
        $infohash = $this->infohash();
        $sizeMB   = number_format($this->size() / (1024 * 1024), 2) . ' MiB';
        $name     = $this->name();
        $media    = $this->media();
        $format   = $this->format();
        $encoding = $this->encoding();
        new Tracker()->update('delete_torrent', [
            'id'        => $this->id,
            'info_hash' => $this->infohashEncoded(),
            'reason'    => $trackerReason,
        ]);

        $manager = new DB();
        $manager->relaxConstraints(true);
        [$ok, $message] = $manager->softDelete(
            MYSQL_DB, 'torrents_leech_stats', 'TorrentID', $this->id, delete: true
        );
        if (!$ok) {
            self::$db->rollback();
            return [false, $message];
        }
        $manager->softDelete(MYSQL_DB, 'torrents', 'ID', $this->id, delete: false);
        $manager->relaxConstraints(false);

        self::$db->prepared_query("
            DELETE FROM torrent_unseeded WHERE torrent_id = ?
            ", $this->id
        );

        self::$db->prepared_query("
            DELETE FROM torrent_unseeded_claim WHERE torrent_id = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM ratelimit_torrent WHERE torrent_id = ?
            ", $this->id
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
        $this->pg()->prepared_query("
            DELETE FROM notification_ticket WHERE id_torrent = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM users_notify_torrents WHERE TorrentID = ?
            ", $this->id
        );

        $deleteKeys = [];
        if (!is_null($user)) {
            $key = sprintf(self::USER_RECENT_UPLOAD, $user->id);
            $recent = self::$cache->get_value($key);
            if (is_array($recent) && in_array($tgroup->id, $recent)) {
                $deleteKeys[] = $key;
            }

            self::$db->prepared_query("
                INSERT INTO user_torrent_remove
                       (user_id, torrent_id)
                VALUES (?,       ?)
                ", $user->id, $this->id
            );
        }

        $userInfo = $user ? " by " . $user->username() : '';
        $this->logger()
            ->torrent(
                $this,
                $user,
                "deleted torrent [$edition] ($media/$format/$encoding $sizeMB $infohash) for reason: $reason"
            )
            ->general(
                "Torrent {$this->id} ($name) [$edition] ($sizeMB $infohash) was deleted$userInfo for reason: $reason"
            );
        parent::remove();
        self::$db->commit();

        self::$cache->delete_multi([
            "torrent_group_{$tgroup->id}",
            sprintf(Manager\Torrent::ID_KEY, $this->id),
            sprintf(self::CACHE_KEY, $this->id),
            ...$deleteKeys
        ]);
        self::$cache->decrement('stats_torrent_count');
        $this->flush();
        $tgroup->refresh();

        self::$db->set_query_id($qid);
        return [true, "torrent " . $this->id . " removed"];
    }
}
