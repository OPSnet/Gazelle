<?php

namespace Gazelle\Manager;

class Torrent extends \Gazelle\Base {
    protected const ID_KEY = 'zz_t_%d';

    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_%d';
    const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    const CACHE_FOLDERNAME         = 'foldername_%s';
    const CACHE_REPORTLIST         = 'reports_torrent_%d';
    const FOLDER_SALT              = "v1\x01";
    const FILELIST_DELIM_UTF8 = "\xC3\xB7";

    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists
    const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    const ARTIST_DISPLAY_TEXT = 1;
    const ARTIST_DISPLAY_HTML = 2;

    protected \Gazelle\User $viewer;

    /**
     * Set the viewer context, for snatched indicators etc.
     * If this is set, and Torrent object created will have it set
     */
    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    public function findById(int $torrentId): ?\Gazelle\Torrent {
        $key = sprintf(self::ID_KEY, $torrentId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM torrents WHERE ID = ?
                ", $torrentId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        if (!$id) {
            return null;
        }
        $torrent = new \Gazelle\Torrent($id);
        if (isset($this->viewer)) {
            $torrent->setViewer($this->viewer);
        }
        return $torrent;
    }

    public function findByInfohash(string $hash) {
        return $this->findById((int)self::$db->scalar("
            SELECT id FROM torrents WHERE info_hash = unhex(?)
            ", $hash
        ));
    }

    /**
     * How many other uploads share the same folder path?
     * NB: Ignore single files that are not in a directory
     *
     * @param string $folder base path in the torrent
     * @return array of Gazelle\Torrent objects;
     */
    public function findAllByFoldername(string $folder): array {
        if ($folder === '') {
            return [];
        }
        $key = sprintf(self::CACHE_FOLDERNAME, md5(self::FOLDER_SALT . $folder));
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT t.ID
                FROM torrents t
                INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                WHERE t.FilePath = ?
                ", $folder
            );
            $list = self::$db->collect(0);
            self::$cache->cache_value($key, $list, 7200);
        }
        $all = [];
        foreach ($list as $id) {
            $torrent = $this->findById($id);
            if ($torrent) {
                $all[] = $torrent;
            }
        }
        return $all;
    }

    public function flushFoldernameCache(string $folder) {
        self::$cache->delete_value(sprintf(self::CACHE_FOLDERNAME, md5($folder)));
    }

    public function missingLogfiles(int $userId): array {
        self::$db->prepared_query("
            SELECT ID FROM torrents WHERE HasLog = '1' AND HasLogDB = '0' AND UserID = ?
            ", $userId
        );
        $torrentIds = self::$db->collect(0, false);

        $result = [];
        foreach ($torrentIds as $torrentId) {
            $result[$torrentId] = $this->findById($torrentId);
        }
        return $result;
    }

    /**
     * Create a string that contains file info in a format that's easy to use for Sphinx
     *
     * @param  string  $Name file path
     * @param  int  $Size file size
     * @return string with the format .EXT sSIZEs NAME DELIMITER
     */
    public function metaFilename(string $name, int $size): string {
        $name = make_utf8(strtr($name, "\n\r\t", '   '));
        $extPos = mb_strrpos($name, '.');
        $ext = $extPos === false ? '' : trim(mb_substr($name, $extPos + 1));
        return sprintf(".%s s%ds %s %s", $ext, $size, $name, self::FILELIST_DELIM_UTF8);
    }

    /**
     *  a meta filename into a more useful array structure
     *
     * @param string meta filename formatted as ".EXT sSIZEs NAME DELIMITER"
     * @return with the keys 'ext', 'size' and 'name'
     */
    public function splitMetaFilename(string $metaname): array {
        preg_match('/^(\.\S+) s(\d+)s (.+) (?:&divide;|' . self::FILELIST_DELIM_UTF8 . ')$/', $metaname, $match);
        return [
            'ext'  => $match[1] ?? null,
            'size' => (int)$match[2] ?? 0,
            // transform leading blanks into hard blanks so that it shows up in HTML
            'name' => preg_replace_callback('/^(\s+)/', function ($s) { return str_repeat('&nbsp;', strlen($s[1])); }, $match[3] ?? ''),
        ];
    }

    /**
     * Create a string that contains file info in the old format for the API
     *
     * @param string $File string with the format .EXT sSIZEs NAME DELIMITER
     * @return string with the format NAME{{{SIZE}}}
     */
    public function apiFilename(string $metaname): string {
        $info = $this->splitMetaFilename($metaname);
        return $info['name'] . '{{{' . $info['size'] . '}}}';
    }

    /**
     * Regenerate a torrent's file list from its meta data,
     * update the database record and clear relevant cache keys
     *
     * @param int torrentId
     * @return int number of files regenned
     */
    public function regenerateFilelist(int $torrentId): int {
        $qid = self::$db->get_query_id();
        $groupId = self::$db->scalar("
            SELECT t.GroupID FROM torrents AS t WHERE t.ID = ?
            ", $torrentId
        );
        $n = 0;
        if ($groupId) {
            $Tor = new \OrpheusNET\BencodeTorrent\BencodeTorrent;
            $Tor->decodeString($str = (new \Gazelle\File\Torrent())->get($torrentId));
            $TorData = $Tor->getData();
            $folderPath = isset($TorData['info']['files']) ? make_utf8($Tor->getName()) : '';
            ['total_size' => $TotalSize, 'files' => $FileList] = $Tor->getFileList();
            $TmpFileList = [];
            foreach ($FileList as $file) {
                $TmpFileList[] = $this->metaFilename($file['path'], $file['size']);
                ++$n;
            }
            self::$db->prepared_query("
                UPDATE torrents SET
                    Size = ?,
                    FilePath = ?,
                    FileList = ?
                WHERE ID = ?
                ", $TotalSize, $folderPath, implode("\n", $TmpFileList),
                $torrentId
            );
            self::$cache->delete_value("torrents_details_$groupId");
            $this->flushFoldernameCache($folderPath);
        }
        self::$db->set_query_id($qid);
        return $n;
    }

    public function setSourceFlag(\OrpheusNET\BencodeTorrent\BencodeTorrent $torrent) {
        $torrentSource = $torrent->getSource();
        if ($torrentSource === SOURCE) {
            return false;
        }
        $creationDate = $torrent->getCreationDate();
        if (!is_null($creationDate)) {
            if (is_null($torrentSource) && $creationDate <= GRANDFATHER_OLD_SOURCE) {
                return false;
            }
            elseif (!is_null($torrentSource) && $torrentSource === GRANDFATHER_SOURCE && $creationDate <= GRANDFATHER_OLD_SOURCE) {
                return false;
            }
        }
        return $torrent->setSource(SOURCE);
    }

    /**
     * Aggregate the audio files per audio type
     *
     * @param string filelist
     * @return array of array of [ac3, flac, m4a, mp3] => count
     */
    function audioMap(string $fileList): array {
        $map = [];
        foreach (explode("\n", strtolower($fileList)) as $file) {
            $info = $this->splitMetaFilename($file);
            if (is_null($info['ext'])) {
                continue;
            }
            $ext = substr($info['ext'], 1); // skip over period
            if (in_array($ext, ['ac3', 'flac', 'm4a', 'mp3'])) {
                if (!isset($map[$ext])) {
                    $map[$ext] = 0;
                }
                ++$map[$ext];
            }
        }
        return $map;
    }

    /**
     * Get the reports associated with a torrent
     * Non-admin users do not see Edited reports
     *
     * @return array of array of [ID, ReporterID, Type, UserComment, ReportedTime]
     */
    public function reportList(\Gazelle\User $viewer, int $torrentId): array {
        $key = sprintf(self::CACHE_REPORTLIST, $torrentId);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT ID,
                    ReporterID,
                    Type,
                    UserComment,
                    ReportedTime
                FROM reportsv2
                WHERE TorrentID = ?
                    AND Status != 'Resolved'",
                $torrentId
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$db->set_query_id($qid);
            self::$cache->cache_value($key, $list, 7200);
        }
        return $viewer->permitted('admin_reports')
            ? $list
            : array_filter($list, function ($report) { return $report['Type'] !== 'edited'; });
    }

    /**
     * Are there any reports associated with this torrent?
     *
     * @param int torrent id
     * @return bool Yes there are
     */
    public function hasReport(\Gazelle\User $viewer, int $torrentId): bool {
        return count($this->reportList($viewer, $torrentId)) > 0;
    }

    /**
     * Record who's seeding how much, used for ratio watch
     */
    public function updateSeedingHistory(): array {
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_users_torrent_history (
                UserID int NOT NULL PRIMARY KEY,
                NumTorrents int NOT NULL DEFAULT 0,
                SumTime bigint NOT NULL DEFAULT 0,
                SeedingAvg int NOT NULL DEFAULT 0,
                KEY numtorrents_idx (NumTorrents)
            ) ENGINE=InnoDB
        ");

        // Find seeders that have announced within the last hour
        self::$db->prepared_query("
            INSERT INTO tmp_users_torrent_history
                (UserID, NumTorrents)
            SELECT uid, COUNT(DISTINCT fid)
            FROM xbt_files_users
            WHERE mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND Remaining = 0
            GROUP BY uid
        ");
        $info = ['new' => self::$db->affected_rows()];

        // Mark new records as "checked" and set the current time as the time
        // the user started seeding <NumTorrents> seeded.
        // Finished = 1 means that the user hasn't been seeding exactly <NumTorrents> earlier today.
        // This query will only do something if the next one inserted new rows last hour.
        self::$db->prepared_query("
            UPDATE users_torrent_history AS h
            INNER JOIN tmp_users_torrent_history AS t ON (t.UserID = h.UserID AND t.NumTorrents = h.NumTorrents)
            SET h.Finished = '0',
                h.LastTime = UNIX_TIMESTAMP(now())
            WHERE h.Finished = '1'
                AND h.Date = UTC_DATE() + 0
        ");
        $info['updated'] = self::$db->affected_rows();

        // Insert new rows for users who haven't been seeding exactly <NumTorrents> torrents earlier today
        // and update the time spent seeding <NumTorrents> torrents for the others.
        // Primary table index: (UserID, NumTorrents, Date).
        self::$db->prepared_query("
            INSERT INTO users_torrent_history
                (UserID, NumTorrents, Date)
            SELECT UserID, NumTorrents, UTC_DATE() + 0
            FROM tmp_users_torrent_history
            ON DUPLICATE KEY UPDATE
                Time = Time + UNIX_TIMESTAMP(now()) - LastTime,
                LastTime = UNIX_TIMESTAMP(now())
        ");
        $info['history'] = self::$db->affected_rows();

        return $info;
    }

    public function storeTop10(string $type, string $key, int $days): int {
        self::$db->prepared_query("
            INSERT INTO top10_history (Type) VALUES (?)
            ", $type
        );
        $historyId = self::$db->inserted_id();

        self::$db->prepared_query("
            SELECT t.ID,
                (t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers) AS score
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            INNER JOIN torrents_group AS tg     ON (tg.ID = t.GroupID)
            WHERE tls.Seeders > 0
                AND t.Time > now() - INTERVAL ? DAY
            GROUP BY tls.Seeders + tls.Leechers
            ORDER BY score DESC, t.ID DESC
            LIMIT ?
            ", $days, 10
        );
        $top10 = self::$db->to_array(false, MYSQLI_NUM, false);

        $rank = 0;
        foreach ($top10 as $torrentId => $score) {
            $torrent = $this->findById($torrentId);
            if ($torrent) {
                self::$db->prepared_query('
                    INSERT INTO top10_history_torrents
                           (HistoryID, sequence, TorrentID, TitleString, TagString)
                    VALUES (?,         ?,        ?,         ?,           ?)
                    ', $historyId, ++$rank, $torrentId, $torrent->fullLink(),
                        implode(' ', $torrent->group()->tagNameList())
                );
            }
        }
        return $rank;
    }

    /**
     * Freeleech / neutral leech / normalise a set of torrents
     *
     * @param array $TorrentIDs An array of torrent IDs to iterate over
     * @param string $leechLevel 0 = normal, 1 = FL, 2 = NL
     * @param string $reason 0 = Unknown, 1 = Staff picks, 2 = Perma-FL (Toolbox, etc.), 3 = Vanity House
     * @param bool $all true = all torrents are made FL, false = only lossless torrents are made FL
     * @param bool $limitLarge true and level is FL => torrents over 1GiB are NL
     */
    public function setFreeleech(\Gazelle\User $user, array $TorrentIDs, string $leechLevel, $reason, bool $all, bool $limitLarge): int {
        $QueryID = self::$db->get_query_id();
        $condition = '';
        if ($reason == '0') {
            if (!$all) {
                $condition .= " AND Encoding IN ('24bit Lossless', 'Lossless')";
            }
            if ($limitLarge) {
                $condition .= " AND Size <= 1073741824"; // 1GiB
            }
        }

        $placeholders = placeholders($TorrentIDs);
        self::$db->begin_transaction();
        self::$db->prepared_query($sql = "
            UPDATE torrents SET
                FreeTorrent = ?, FreeLeechType = ?
            WHERE ID IN ($placeholders)
                $condition
            ", $leechLevel, $reason, ...$TorrentIDs
        );
        $affected = self::$db->affected_rows();

        if ($reason == '0' && $limitLarge) {
            self::$db->prepared_query("
                UPDATE torrents SET
                    FreeTorrent = ?, FreeLeechType = ?
                WHERE ID IN ($placeholders)
                    AND Size > 1073741824
                ", '2', $reason, ...$TorrentIDs
            );
            $affected += self::$db->affected_rows();
        }

        self::$db->prepared_query("
            SELECT ID, GroupID, info_hash FROM torrents WHERE ID IN ($placeholders) ORDER BY GroupID ASC
            ", ...$TorrentIDs
        );
        $Torrents = self::$db->to_array(false, MYSQLI_NUM, false);
        $GroupIDs = self::$db->collect('GroupID', false);
        self::$db->commit();
        self::$db->set_query_id($QueryID);

        $tgMan = new TGroup;
        foreach ($GroupIDs as $id) {
            $tgMan->refresh($id);
        }

        $groupLog = new \Gazelle\Log;
        $tracker = new \Gazelle\Tracker;
        foreach ($Torrents as list($TorrentID, $GroupID, $InfoHash)) {
            $tracker->update_tracker('update_torrent', ['info_hash' => rawurlencode($InfoHash), 'freetorrent' => $leechLevel]);
            self::$cache->delete_value("torrent_download_$TorrentID");
            $groupLog->torrent($GroupID, $TorrentID, $user->id(), "marked as freeleech type $reason")
                ->general($user->username() . " marked torrent $TorrentID freeleech type $reason");
        }

        return $affected;
    }

    public function updatePeerlists(): array {
        self::$cache->disableLocalCache();
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_torrents_peerlists (
                TorrentID int NOT NULL PRIMARY KEY,
                GroupID   int,
                Seeders   int,
                Leechers  int,
                Snatches  int
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_torrents_peerlists
            SELECT t.ID, t.GroupID, tls.Seeders, tls.Leechers, tls.Snatched
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        ");

        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tpc_temp (
                TorrentID int,
                GroupID   int,
                Seeders   int,
                Leechers  int,
                Snatched  int,
                PRIMARY KEY (GroupID, TorrentID)
            )
        ");
        self::$db->prepared_query("
            INSERT INTO tpc_temp
            SELECT t2.*
            FROM torrents_peerlists AS t1
            INNER JOIN tmp_torrents_peerlists AS t2 USING (TorrentID)
            WHERE t1.Seeders != t2.Seeders
                OR t1.Leechers != t2.Leechers
                OR t1.Snatches != t2.Snatches
        ");

        $StepSize = 30000;
        self::$db->prepared_query("
            SELECT TorrentID, GroupID, Seeders, Leechers, Snatched
            FROM tpc_temp
            ORDER BY GroupID ASC, TorrentID ASC
            LIMIT ?
            ", $StepSize
        );

        $RowNum = 0;
        $LastGroupID = 0;
        $UpdatedKeys = $UncachedGroups = 0;
        [$TorrentID, $GroupID, $Seeders, $Leechers, $Snatches] = self::$db->next_record(MYSQLI_NUM, false);
        while ($TorrentID) {
            if ($LastGroupID != $GroupID) {
                $CachedData = self::$cache->get_value("torrent_group_$GroupID");
                if ($CachedData !== false) {
                    if (isset($CachedData["ver"]) && $CachedData["ver"] == \Gazelle\Cache::GROUP_VERSION) {
                        $CachedStats = &$CachedData["d"]["Torrents"];
                    }
                } else {
                    $UncachedGroups++;
                }
                $LastGroupID = $GroupID;
            }
            while ($LastGroupID == $GroupID) {
                $RowNum++;
                if (isset($CachedStats) && is_array($CachedStats[$TorrentID])) {
                    $OldValues = &$CachedStats[$TorrentID];
                    $OldValues["Seeders"] = $Seeders;
                    $OldValues["Leechers"] = $Leechers;
                    $OldValues["Snatched"] = $Snatches;
                    $Changed = true;
                    unset($OldValues);
                }
                if (!($RowNum % $StepSize)) {
                    self::$db->prepared_query("
                        SELECT TorrentID, GroupID, Seeders, Leechers, Snatched
                        FROM tpc_temp
                        WHERE (GroupID > ? OR (GroupID = ? AND TorrentID > ?))
                        ORDER BY GroupID ASC, TorrentID ASC
                        LIMIT ?
                        ", $GroupID, $GroupID, $TorrentID, $StepSize
                    );
                }
                $LastGroupID = $GroupID;
                [$TorrentID, $GroupID, $Seeders, $Leechers, $Snatches] = self::$db->next_record(MYSQLI_NUM, false);
            }
            if ($Changed) {
                self::$cache->cache_value("torrent_group_$LastGroupID", $CachedData, 7200);
                unset($CachedStats);
                $UpdatedKeys++;
                $Changed = false;
            }
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM torrents_peerlists");
        self::$db->prepared_query("
            INSERT INTO torrents_peerlists
            SELECT *
            FROM tmp_torrents_peerlists
        ");
        self::$db->commit();
        return [$UpdatedKeys, $UncachedGroups];
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
     */
    public function flushLatestUploads(int $limit) {
        self::$cache->delete_value(sprintf(self::CACHE_KEY_LATEST_UPLOADS, $limit));
    }

    /**
     * Return the N most recent lossless uploads
     * Note that if both a Lossless and 24bit Lossless are uploaded at the same time,
     * only one entry will be returned, to ensure that the result is comprised of N
     * different groups. Uploads of paranoid and disabled users are excluded.
     * Uploads without cover art are excluded.
     *
     * @return array of \Gazelle\TGroup objects
     */
    public function latestUploads(int $limit): array {
        $key = sprintf(self::CACHE_KEY_LATEST_UPLOADS, $limit);
        $latest = self::$cache->get_value($key);
        if ($latest === false) {
            self::$db->prepared_query("
                SELECT t.GroupID                    AS groupId,
                    t.ID                            AS torrentId,
                    coalesce(um.Paranoia, 'a:0:{}') AS paranoia
                FROM torrents t
                INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                INNER JOIN users_main     um ON (um.ID = t.UserID)
                WHERE t.Time > now() - INTERVAL 3 DAY
                    AND t.Encoding IN ('Lossless', '24bit Lossless')
                    AND tg.WikiImage != ''
                    AND um.Enabled = '1'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM torrents_tags ttex
                        WHERE t.GroupID = ttex.GroupID
                            AND ttex.TagID IN (" . placeholders(HOMEPAGE_TAG_IGNORE) . ")
                    )
                ORDER BY t.Time DESC
                ", ...HOMEPAGE_TAG_IGNORE
            );
            $latest = [];
            $seen = [];
            $max = self::$db->record_count();
            $nr = 0;
            while ($nr < min($limit, $max)) {
                $row = self::$db->next_record(MYSQLI_ASSOC, false);
                if (!$row) {
                    break;
                }
                if (in_array($row['groupId'], $seen)) {
                    continue;
                }
                if (in_array('uploads', unserialize($row['paranoia']))) {
                    continue;
                }
                $torrent = $this->findById($row['torrentId']);
                if (is_null($torrent) || !$torrent->group()->image()) {
                    continue;
                }
                $seen[]   = $row['groupId'];
                $latest[] = $row['torrentId'];
                ++$nr;
            }
            self::$cache->cache_value($key, $latest, 86400);
        }
        return array_map(fn($id) => $this->findById($id), $latest);
    }
}
