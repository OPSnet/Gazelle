<?php

namespace Gazelle\Manager;

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class Torrent extends \Gazelle\BaseManager {
    protected const CACHE_HIST = 'top10_hist_%s_%s';

    final public const ID_KEY = 'zz_t_%d';
    final public const CACHE_KEY_LATEST_UPLOADS = 'latest_up_%d';
    final protected const CACHE_FOLDERNAME      = 'foldername_%s';

    public function create(
        \Gazelle\TGroup $tgroup,
        \Gazelle\User   $user,
        string          $description,
        string          $media,
        ?string         $format,
        ?string         $encoding,
        string          $infohash,
        string          $filePath,
        array           $fileList,
        int             $size,
        bool            $isScene,
        ?int            $remasterYear,
        string          $remasterTitle,
        string          $remasterRecordLabel,
        string          $remasterCatalogueNumber,
        int             $logScore     = 0,
        bool            $hasChecksum = false,
        bool            $hasCue      = false,
        bool            $hasLog      = false,
        bool            $hasLogInDB  = false,
    ): \Gazelle\Torrent {
        self::$db->prepared_query("
            INSERT INTO torrents (
                GroupID, UserID, Media, Format, Encoding, RemasterYear, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber,
                info_hash, Scene, LogScore, LogChecksum, HasLog, HasCue, HasLogDB, FilePath, FileCount, FileList,
                Size, Description
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?
            )", $tgroup->id, $user->id, $media, $format, $encoding,
                $remasterYear, $remasterTitle, $remasterRecordLabel, $remasterCatalogueNumber,
                $infohash, $isScene ? '1' : '0', $logScore, $hasChecksum ? '1' : '0', $hasLog ? '1' : '0',
                $hasCue ? '1' : '0', $hasLogInDB ? '1' : '0', $filePath, count($fileList), implode("\n", $fileList),
                $size, $description,
        );
        $torrent = $this->findById(self::$db->inserted_id());
        self::$db->prepared_query('
            INSERT INTO torrents_leech_stats (TorrentID) VALUES (?)
            ', $torrent->id
        );
        $tgroup->flush();
        $torrent->lockUpload();
        $torrent->flushFoldernameCache();
        $user->flushRecentUpload();

        // Flush the most recent uploads when a new lossless upload is made
        if (in_array($encoding, ['Lossless', '24bit Lossless'])) {
            self::$cache->delete_value(sprintf(self::CACHE_KEY_LATEST_UPLOADS, 5));
        }
        return $torrent;
    }

    public function findById(int $id): ?\Gazelle\Torrent {
        $key = sprintf(self::ID_KEY, $id);
        $torrentId = self::$cache->get_value($key);
        if ($torrentId === false) {
            $torrentId = self::$db->scalar("
                SELECT ID FROM torrents WHERE ID = ?
                ", $id
            );
            if ($torrentId) {
                self::$cache->cache_value($key, $torrentId, 7200);
            }
        }
        return $torrentId ? new \Gazelle\Torrent($torrentId) : null;
    }

    public function findDeletedById(int $torrentId): ?\Gazelle\TorrentDeleted {
        $found = (bool)self::$db->scalar("
            SELECT 1 FROM deleted_torrents WHERE ID = ?
            ", $torrentId
        );
        return $found ? new \Gazelle\TorrentDeleted($torrentId) : null;
    }

    public function findByInfohash(string $hash): ?\Gazelle\Torrent {
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
        $key = sprintf(self::CACHE_FOLDERNAME, signature($folder, FOLDER_CLASH_SALT));
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

    public function lookupLeechReason(string $leechReason): LeechReason {
        return match ($leechReason) {
            LeechReason::AlbumOfTheMonth->value => LeechReason::AlbumOfTheMonth,
            LeechReason::Permanent->value       => LeechReason::Permanent,
            LeechReason::Showcase->value        => LeechReason::Showcase,
            LeechReason::StaffPick->value       => LeechReason::StaffPick,
            default                             => LeechReason::Normal,
        };
    }

    public function lookupLeechType(string $leechType): LeechType {
        return match ($leechType) {
            LeechType::Free->value    => LeechType::Free,
            LeechType::Neutral->value => LeechType::Neutral,
            default                   => LeechType::Normal,
        };
    }

    public function leechReasonList(): array {
        return [
            LeechReason::Normal,
            LeechReason::AlbumOfTheMonth,
            LeechReason::Permanent,
            LeechReason::Showcase,
            LeechReason::StaffPick,
        ];
    }

    public function leechTypeList(): array {
        return [
            LeechType::Normal,
            LeechType::Neutral,
            LeechType::Free,
        ];
    }

    public function flushFoldernameCache(string $folder): void {
        self::$cache->delete_value(sprintf(self::CACHE_FOLDERNAME, signature($folder, FOLDER_CLASH_SALT)));
    }

    public function missingLogfiles(int $userId): array {
        self::$db->prepared_query("
            SELECT ID FROM torrents WHERE HasLog = '1' AND HasLogDB = '0' AND UserID = ?
            ", $userId
        );
        $torrentIds = self::$db->collect(0);

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
        $torrentIds = self::$db->collect(0);

        $result = [];
        foreach ($torrentIds as $torrentId) {
            $torrent = $this->findById($torrentId);
            if ($torrent) {
                $result[$torrentId] = [
                    'id'   => $torrent->id,
                    'link' => $torrent->fullLink(),
                ];
            }
        }
        return $result;
    }

    /**
     * Create a string that contains file info in a format that's easy to use for Sphinx
     * NB: This is defined here because upload_handle needs it before a torrent is created
     *
     * @return string with the format .EXT sSIZEs NAME DELIMITER
     */
    public function metaFilename(string $name, int $size): string {
        $name = make_utf8(strtr($name, "\n\r\t", '   '));
        $extPos = mb_strrpos($name, '.');
        $ext = $extPos === false ? '' : trim(mb_substr($name, $extPos + 1));
        return sprintf(".%s s%ds %s %s", $ext, $size, $name, FILELIST_DELIM);
    }

    public function setSourceFlag(\OrpheusNET\BencodeTorrent\BencodeTorrent $torrent): bool {
        $sourceFlag = $torrent->getSource();
        if ($sourceFlag === SOURCE_FLAG) {
            // appropriate source flag for the era, no change
            return false;
        }
        $createdEpoch = $torrent->getCreationDate();
        if (!is_null($createdEpoch)) {
            if (is_null($sourceFlag) && $createdEpoch <= SOURCE_FLAG_NONE_EPOCH) {
                // no source flag but prior to the introduction of source flags, no change
                return false;
            } elseif (
                // A prior source flag is present and corresponds to the name in use
                // at the time, no change
                !is_null($sourceFlag)
                && $sourceFlag === SOURCE_PREV_FLAG
                && $createdEpoch <= SOURCE_FLAG_PREV_EPOCH
            ) {
                return false;
            }
        }
        // capture the flag
        return $torrent->setSource(SOURCE_FLAG);
    }

    /**
     * Freeleech / neutral leech / normalise a list of torrents
     * NB: If FLAC-only filtering is required, it must be handled by the calling code.
     */
    public function setListFreeleech(
        \Gazelle\Tracker $tracker,
        \Gazelle\User    $user,
        array            $idList,
        LeechType        $leechType,
        LeechReason      $reason
    ): int {
        $placeholders = placeholders($idList);
        self::$db->prepared_query("
            UPDATE torrents SET
                FreeTorrent = ?, FreeLeechType = ?
            WHERE ID IN ($placeholders)
            ", $leechType->value, $reason->value, ...$idList
        );
        $affected = self::$db->affected_rows();

        $refresh = [];
        foreach ($idList as $torrentId) {
            $torrent = $this->findById($torrentId)->flush();
            $tracker->modifyTorrent($torrent, $leechType);
            $tgroupId = $torrent->groupId();
            if (!isset($refresh[$tgroupId])) {
                $refresh[$tgroupId] = $torrent->group();
            }
            $this->logger()
                ->torrent(
                    $torrent,
                    $user,
                    "marked as freeleech type {$reason->label()}"
                )
                ->general(
                    "{$user->username()} marked torrent $torrentId freeleech type {$reason->label()}"
                );
        }
        foreach ($refresh as $tgroup) {
            $tgroup->refresh();
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
        [$TorrentID, $GroupID, $Seeders, $Leechers, $Snatches] = self::$db->next_record(MYSQLI_NUM);
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
                [$TorrentID, $GroupID, $Seeders, $Leechers, $Snatches] = self::$db->next_record(MYSQLI_NUM);
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
     * Return the N most recent lossless uploads
     * Note that if both a Lossless and 24bit Lossless are uploaded at the same time,
     * only one entry will be returned, to ensure that the result is comprised of N
     * different groups. Uploads of paranoid and disabled users are excluded.
     * Uploads without cover art are excluded.
     *
     * @return array of \Gazelle\Torrent objects
     */
    public function latestUploads(int $limit): array {
        $key    = sprintf(self::CACHE_KEY_LATEST_UPLOADS, $limit);
        $latest = self::$cache->get_value($key);
        $retry  = 2;
        while ($retry--) {
            if ($latest === false) {
                self::$db->prepared_query("
                    SELECT t.GroupID                    AS group_id,
                        t.ID                            AS torrent_id,
                        coalesce(um.Paranoia, 'a:0:{}') AS paranoia
                    FROM torrents t
                    INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                    INNER JOIN users_main     um ON (um.ID = t.UserID)
                    WHERE t.created > now() - INTERVAL 3 DAY
                        AND t.Encoding IN ('Lossless', '24bit Lossless')
                        AND tg.WikiImage REGEXP '^https?://[^/]+/.*'
                        AND um.Enabled = '1'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM torrents_tags ttex
                            WHERE t.GroupID = ttex.GroupID
                                AND ttex.TagID IN (" . placeholders(HIDDEN_TAGS) . ")
                        )
                    ORDER BY t.created DESC
                    ", ...HIDDEN_TAGS
                );
                $latest = [];
                $seen   = [];
                $nr     = 0;
                $qid    = self::$db->get_query_id();
                $max    = self::$db->record_count();
                while ($nr < min($limit, $max)) {
                    self::$db->set_query_id($qid);
                    $row = self::$db->next_record(MYSQLI_ASSOC);
                    if (is_null($row)) {
                        break;
                    }
                    if (in_array($row['group_id'], $seen)) {
                        continue;
                    }
                    if (in_array('uploads', unserialize($row['paranoia']))) {
                        continue;
                    }
                    $torrent = $this->findById($row['torrent_id']);
                    if (is_null($torrent) || !$torrent->group()->image()) {
                        continue;
                    }
                    $seen[]   = $row['group_id'];
                    $latest[] = $row['torrent_id'];
                    ++$nr;
                }
                self::$cache->cache_value($key, $latest, 3600);
            }
            // we either have a fresh list of ids, or it came from the cache
            // reinstantiate the list as Torrent objects
            $list = array_filter(
                array_map(fn($id) => $this->findById($id), $latest),
                fn($t) => $t instanceof \Gazelle\Torrent && $t->group()->image() !== ''
            );
            if (count($list) == count($latest)) {
                return $list;
            }
            // a torrent was deleted since the list was cached: must regenerate a new list
            $latest = false;
        }
        return [];
    }

    /**
     * This is a bit of an ugly hack, used to render PL bbcode links from the \Text class
     */

    public static function renderPL(int $id, array $attr): ?string {
        $torrent = new self()->findById($id);
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
            $tgroup = new \Gazelle\Manager\TGroup()->findById((int)$deleted['GroupID']);
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
            $tgroup->hashTag(), $tgroup->id, $id, $id, display_str($tgroup->name()), $label,
            $meta . ($isDeleted ? ' <i>deleted</i>' : '')
        );
    }

    protected static function metaPL(
        string $media,
        string $format,
        string $encoding,
        bool $hasCue,
        bool $hasLog,
        bool $hasLogDb,
        int $logScore,
    ): string {
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

    public function topTenCacheKey(string $datetime, bool $isByDay): string {
        return sprintf(self::CACHE_HIST,
            $isByDay ? 'day' : 'week',
            preg_replace('/\D+/', '', $datetime)
        );
    }

    public function storeTop10(string $type, int $days): int {
        self::$db->prepared_query("
            INSERT INTO top10_history (Type) VALUES (?)
            ", $type
        );
        $historyId = self::$db->inserted_id();

        self::$db->prepared_query("
            SELECT t.ID
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            WHERE t.Size > 0
                AND tls.Seeders > 0
                AND t.created > now() - INTERVAL ? DAY
            ORDER BY ln(t.Size) * tls.Snatched + ln(t.Size) * tls.Leechers DESC, t.ID DESC
            LIMIT 20
            ", $days
        );

        $sequence = 0;
        foreach (self::$db->collect(0) as $torrentId) {
            $torrent = $this->findById($torrentId);
            if ($torrent) {
                self::$db->prepared_query("
                    INSERT INTO top10_history_torrents
                           (HistoryID, sequence, TorrentID)
                    VALUES (?,         ?,        ?)
                    ", $historyId, ++$sequence, $torrentId
                );
            }
        }
        return $historyId;
    }

    public function topTenHistoryList(string $datetime, bool $isByDay): array {
        $key = $this->topTenCacheKey($datetime, $isByDay);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT tht.sequence,
                    tht.TorrentID AS torrent_id
                FROM top10_history th
                INNER JOIN top10_history_torrents tht ON (tht.HistoryID = th.ID)
                INNER JOIN torrents t ON (t.ID = tht.TorrentID)
                WHERE th.Type    = ?
                    AND th.Date >= date(?)
                    AND th.Date <  date(?) + INTERVAL ? DAY
                ORDER BY tht.sequence ASC
                ", $isByDay ? 'Daily' : 'Weekly', $datetime, $datetime, $isByDay ? 1 : 7
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC);
            self::$cache->cache_value($key, $list, 3600 * 24);
        }
        foreach ($list as &$entry) {
            $entry['torrent'] = $this->findById($entry['torrent_id']);
        }
        return $list;
    }

    public function resetReseededRequest(): int {
        self::$db->prepared_query("
            UPDATE torrents AS t
            LEFT JOIN torrents_leech_stats AS tls ON t.ID = tls.TorrentID
            SET t.LastReseedRequest = NULL
            WHERE t.LastReseedRequest <= (now() - INTERVAL " . RESEED_NEVER_ACTIVE_TORRENT . " DAY)
                AND tls.last_action IS NULL
        ");
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE torrents SET
                LastReseedRequest = NULL
            WHERE LastReseedRequest <= (now() - INTERVAL " . RESEED_TORRENT . " DAY)
        ");
        return $affected + self::$db->affected_rows();
    }
}
