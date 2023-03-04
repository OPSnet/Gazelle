<?php

namespace Gazelle\Manager;

class Torrent extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_t_%d';

    final const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_%d';
    final const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    final const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    final const CACHE_FOLDERNAME         = 'foldername_%s';
    final const FOLDER_SALT              = "v1\x01";

    final const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists
    final const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    final const ARTIST_DISPLAY_TEXT = 1;
    final const ARTIST_DISPLAY_HTML = 2;

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

    public function findDeletedById(int $torrentId): ?\Gazelle\TorrentDeleted {
        $found = (bool)self::$db->scalar("
            SELECT 1 FROM deleted_torrents WHERE ID = ?
            ", $torrentId
        );
        return $found ? new \Gazelle\TorrentDeleted($torrentId) : null;
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

    public function create(
        int     $tgroupId,
        int     $userId,
        string  $description,
        string  $media,
        ?string $format,
        ?string $encoding,
        string  $infohash,
        string  $filePath,
        array   $fileList,
        int     $size,
        bool    $isScene,
        bool    $isRemaster,
        ?int    $remasterYear,
        string  $remasterTitle,
        string  $remasterRecordLabel,
        string  $remasterCatalogueNumber,
        int     $logScore     = 0,
        bool    $hasChecksum = false,
        bool    $hasCue      = false,
        bool    $hasLog      = false,
        bool    $hasLogInDB  = false,
    ): \Gazelle\Torrent {
        self::$db->prepared_query("
            INSERT INTO torrents (
                GroupID, UserID, Media, Format, Encoding, Remastered, RemasterYear, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber,
                info_hash, Scene, LogScore, LogChecksum, HasLog, HasCue, HasLogDB, FilePath, FileCount, FileList,
                Size, Description
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?
            )", $tgroupId, $userId, $media, $format, $encoding,
                $isRemaster, $remasterYear, $remasterTitle, $remasterRecordLabel, $remasterCatalogueNumber,
                $infohash, $isScene ? '1': '0', $logScore, $hasChecksum ? '1' : '0', $hasLog ? '1' : '0',
                $hasCue ? '1' : '0', $hasLogInDB ? '1' : '0', $filePath, count($fileList), implode("\n", $fileList),
                $size, $description,
        );
        $torrent = $this->findById(self::$db->inserted_id());
        self::$db->prepared_query('
            INSERT INTO torrents_leech_stats (TorrentID) VALUES (?)
            ', $torrent->id()
        );
        $torrent->lockUpload();
        return $torrent;
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
     * Return a list of all CD+Log uploads of a user. As this list can be large.
     * only id + name fields are returned to avoid excessive memory consumption.
     * Should probably become paginated.
     *
     * @return array of ['id', 'link']
     */
    public function logFileList(int $userId): array {
        self::$db->prepared_query("
            SELECT ID FROM torrents WHERE HasLog = '1' AND HasLogDB = '1' AND UserID = ?
            ", $userId
        );
        $torrentIds = self::$db->collect(0, false);

        $result = [];
        foreach ($torrentIds as $torrentId) {
            $torrent = $this->findById($torrentId);
            if ($torrent) {
                $result[$torrentId] = [
                    'id'   => $torrent->id(),
                    'link' => $torrent->fullLink(),
                ];
            }
        }
        return $result;
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

    /**
     * Regenerate a torrent's file list from its meta data,
     * update the database record and clear relevant cache keys
     *
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
     * Record who's seeding how much, used for ratio watch
     */
    public function updateSeedingHistory(): array {
        self::$db->dropTemporaryTable("tmp_users_torrent_history");
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
        self::$db->dropTemporaryTable("tmp_users_torrent_history");
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
            $tgMan->findById($id)->refresh();
        }

        $groupLog = new \Gazelle\Log;
        $tracker = new \Gazelle\Tracker;
        foreach ($Torrents as [$TorrentID, $GroupID, $InfoHash]) {
            $tracker->update_tracker('update_torrent', ['info_hash' => rawurlencode($InfoHash), 'freetorrent' => $leechLevel]);
            self::$cache->delete_value("torrent_download_$TorrentID");
            $groupLog->torrent($GroupID, $TorrentID, $user->id(), "marked as freeleech type $reason")
                ->general($user->username() . " marked torrent $TorrentID freeleech type $reason");
        }

        return $affected;
    }

    public function updatePeerlists(): array {
        self::$db->prepared_query("
            DELETE FROM xbt_files_users
            WHERE mtime < unix_timestamp(NOW() - INTERVAL ? SECOND)
            ", UNSEEDED_DRAIN_INTERVAL
        );
        $purged = self::$db->affected_rows();
        self::$db->dropTemporaryTable("tmp_torrents_peerlists");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_torrents_peerlists (
                TorrentID int NOT NULL PRIMARY KEY,
                GroupID   int,
                Seeders   int,
                Leechers  int,
                Snatches  int
            )
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_torrents_peerlists
            SELECT t.ID, t.GroupID, tls.Seeders, tls.Leechers, tls.Snatched
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        ");

        self::$db->dropTemporaryTable("tpc_temp");
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
            $Changed = false;
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
            if (isset($CachedData) && $Changed) {
                self::$cache->cache_value("torrent_group_$LastGroupID", $CachedData, 7200);
                unset($CachedStats);
                $UpdatedKeys++;
                $Changed = false;
            }
        }
        self::$db->dropTemporaryTable("tpc_temp");

        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM torrents_peerlists");
        self::$db->prepared_query("
            INSERT INTO torrents_peerlists
            SELECT *
            FROM tmp_torrents_peerlists
        ");
        self::$db->commit();
        self::$db->dropTemporaryTable("tmp_torrents_peerlists");
        return [$UpdatedKeys + $purged, $UncachedGroups];
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
            $qid = self::$db->get_query_id();
            while ($nr < min($limit, $max)) {
                self::$db->set_query_id($qid);
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

    /**
     * This is a bit of an ugly hack, used to render PL bbcode links from the \Text class
     */

    public static function renderPL(int $id, array $attr): ?string {
        $torrent = (new self)->findById($id);
        $meta = '';
        $wantMeta = !(in_array('nometa', $attr) || in_array('title', $attr));

        if (!is_null($torrent)) {
            $tgroup = $torrent->group();
            if ($wantMeta && $tgroup->categoryName() === 'Music') {
                $meta = self::metaPL(
                    $torrent->media(), $torrent->format(), $torrent->encoding(),
                    $torrent->hasCue(), $torrent->hasLog(), $torrent->hasLogDb(), $torrent->logScore()
                );
            }
            $isDeleted = false;
        } else {
            $deleted = self::$db->rowAssoc("
                SELECT GroupID, Format, Encoding, Media, HasCue, HasLog, HasLogDB, LogScore
                FROM deleted_torrents
                WHERE ID = ?
                ", $id
            );
            if (is_null($deleted)) {
                return null;
            }
            $tgroup = (new \Gazelle\Manager\TGroup)->findById((int)$deleted['GroupID']);
            if (is_null($tgroup)) {
                return null;
            }
            if ($wantMeta && $tgroup->categoryName() === 'Music') {
                $meta = self::metaPL(
                    $deleted['Media'], $deleted['Format'], $deleted['Encoding'],
                    (bool)$deleted['HasCue'], (bool)$deleted['HasLog'], (bool)$deleted['HasLogDB'], (int)$deleted['LogScore']
                );
            }
            $isDeleted = true;
        }
        $year = in_array('noyear', $attr) || in_array('title', $attr) ? '' : $tgroup->year();
        $releaseType = ($tgroup->categoryName() !== 'Music' || in_array('noreleasetype', $attr) || in_array('title', $attr))
            ? '' : $tgroup->releaseTypeName();
        $label = ($year || $releaseType) ? (' [' . trim(implode(' ', [$year, $releaseType])) . ']') : '';
        $url = '';
        if (!(in_array('noartist', $attr) || in_array('title', $attr))) {
            if ($tgroup->categoryName() === 'Music') {
                $url = $tgroup->artistRole()->link() . " – ";
            }
        }
        return $url . sprintf(
            '<a title="%s" href="/torrents.php?id=%d&torrentid=%d#torrent%d">%s%s</a>%s',
            $tgroup->hashTag(), $tgroup->id(), $id, $id, display_str($tgroup->name()), $label,
            $meta . ($isDeleted ? ' <i>deleted</i>' : '')
        );
    }

    protected static function metaPL(string $media, string $format, string $encoding, bool $hasCue, bool $hasLog, bool $hasLogDb, int $logScore) {
        $meta = [$media, $format, $encoding];
        if ($hasCue) {
            $meta[] = 'Cue';
        }
        if ($hasLog) {
            $log = 'Log';
            if ($hasLogDb) {
                $log .= " ({$logScore}%)";
            }
            $meta[] = "$log";
        }
        return ' ' . implode('/', $meta);
    }
}
