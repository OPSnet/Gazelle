<?php

namespace Gazelle;

class Torrent extends BaseObject {

    const CACHE_KEY                = 't2_%d';
    const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    const USER_RECENT_UPLOAD       = 'u_recent_up_%d';

    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists

    protected TGroup $tgroup;
    protected bool $isDeleted = false;
    protected $showSnatched;
    protected $snatchBucket;
    protected $tokenCache;
    protected $updateTime;
    protected User $viewer;

    public function tableName(): string {
        return 'torrents';
    }

    public function flush() {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
    }

    public function url(): string {
        return "torrents.php?groupId=" . $this->groupId() . '&torrentid=' . $this->id . '#' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->group()->name()));
    }

    public function fullLink(): string {
        $link = $this->group()->link();
        $edition = $this->edition();
        if ($edition) {
            $link .= " [$edition]";
        }
        $label = $this->label();
        if ($label) {
            $link .= " [$label]";
        }
        return $link;
    }

    public function name(): string {
        $tgroup = $this->group();
        return $tgroup->categoryId() === 1
            ? $tgroup->artistName() . " \xE2\x80\x93 " . $tgroup->name()
            : $tgroup->name();
    }

    public function fullName(): string {
        $name = $this->name();
        $edition = $this->edition();
        if ($edition) {
            $name .= " [$edition]";
        }
        return $name;
    }

    /**
     * Set the viewer context, for snatched indicators etc.
     */
    public function setViewer(User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * In the context of a user, determine whether snatched indicators should be
     * added to torrent and group info.
     */
    public function setShowSnatched(int $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    /**
     * How many tokens are required to download for free?
     */
    public function tokenCount(): int {
        return ceil($this->size() / BYTES_PER_FREELEECH_TOKEN);
    }

    /**
     * Check if the viewer has an active freeleech token on a torrent
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

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(): array {
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $template = "SELECT t.GroupID, t.UserID, t.Media, t.Format, t.Encoding,
                    t.Remastered, t.RemasterYear, t.RemasterTitle, t.RemasterCatalogueNumber, t.RemasterRecordLabel,
                    t.Scene, t.HasLog, t.HasCue, t.HasLogDB, t.LogScore, t.LogChecksum,
                    hex(t.info_hash) as info_hash, t.info_hash as info_hash_raw,
                    t.FileCount, t.FileList, t.FilePath, t.Size,
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
            $info = self::$db->rowAssoc(str_replace('%table%', 'torrents', $template), $this->id);
            if (is_null($info)) {
                $info = self::$db->rowAssoc(str_replace('%table%', 'deleted_torrents', $template), $this->id);
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

            self::$cache->cache_value($key, $info, ($info['Seeders'] ?? 0) > 0 ? 600 : 3600);
        }

        if (isset($this->viewer)) {
            $info['PersonalFL'] = $info['FreeTorrent'] == '0' && $this->hasToken($this->viewer->id());
            $info['IsSnatched'] = $this->showSnatched && $this->viewer->option('ShowSnatched') && $this->isSnatched($this->viewer->id());
        } else {
            $info['PersonalFL'] = false;
            $info['IsSnatched'] = false;
        }

        return $info;
    }

    /**
     * Generate the edition of the torrent
     */
    public function edition(): string {
        $tgroup = $this->group();
        return implode(' / ', array_filter(
            [
                $this->remasterYear() ?: $tgroup->year(),
                $this->remasterTitle(),
                $this->remasterRecordLabel() ?? $tgroup->recordLabel(),
                $this->remasterCatalogueNumber() ?? $tgroup->catalogueNumber(),
            ],
            fn($element) => !is_null($element)
        ));
    }

    protected function labelElement($class, $text): string {
        return sprintf('<strong class="torrent_label tooltip %s" title="%s" style="white-space: nowrap;">%s</strong>',
            $class, $text, $text
        );
    }

    public function shortLabelList(): array {
        $info = $this->info();
        $label = [];
        if (!empty($info['Media'])) {
            $label[] = $info['Media'];
        }
        if (!empty($info['Format'])) {
            $label[] = $info['Format'];
        }
        if (!empty($info['Encoding'])) {
            $label[] = $info['Encoding'];
        }
        if ($info['Media'] === 'CD') {
            if ($info['HasLog']) {
                $label[] = ($info['HasLogDB'] ? "{$info['LogScore']}% " : '') . 'Log';
            }
            if ($info['HasCue']) {
                $label[] = 'Cue';
            }
        }
        if ($info['Scene']) {
            $label[] = 'Scene';
        }
        return $label;
    }

    public function labelList(): array {
        $info = $this->info();
        $label = $this->shortLabelList();

        if (isset($this->viewer) && $this->isSnatched($this->viewer->id())) {
            $label[] = $this->labelElement('tl_snatched', 'Snatched!');
        }
        if (isset($info['FreeTorrent'])) {
            if ($info['FreeTorrent'] == '1') {
                $label[] = $this->labelElement('tl_free', 'Freeleech!');
            } elseif ($info['FreeTorrent'] == '2') {
                $label[] = $this->labelElement('tl_free tl_neutral', 'Neutral Leech!');
            }
        } elseif ($info['PersonalFL']) {
            $label[] = $this->labelElement('tl_free tl_personal', 'Personal Freeleech!');
        }
        if (isset($info['Reported']) && $info['Reported']) {
            $label[] = $this->labelElement('tl_reported', 'Reported');
        }
        if ($info['Media'] === 'CD' && $info['HasLog'] && $info['HasLogDB'] && !$info['LogChecksum']) {
            $label[] = $this->labelElement('tl_notice', 'Bad/Missing Checksum');
        }
        if ($this->hasBadTags()) {
            $label[] = $this->labelElement('tl_reported tl_bad_tags', 'Bad Tags');
        }
        if ($this->hasBadFolders()) {
            $label[] = $this->labelElement('tl_reported tl_bad_folders', 'Bad Folders');
        }
        if ($this->hasBadFiles()) {
            $label[] = $this->labelElement('tl_reported tl_bad_filenames', 'Bad File Names');
        }
        if ($this->hasMissingLineage()) {
            $label[] = $this->labelElement('tl_reported tl_missing_lineage', 'Missing Lineage');
        }
        if ($this->hasCassetteApproved()) {
            $label[] = $this->labelElement('tl_approved tl_cassette', 'Cassette Approved');
        }
        if ($this->hasLossymasterApproved()) {
            $label[] = $this->labelElement('tl_approved tl_lossy_master', 'Lossy Master Approved');
        }
        if ($this->hasLossywebApproved()) {
            $label[] = $this->labelElement('tl_approved tl_lossy_web', 'Lossy WEB Approved');
        }
        return $label;
    }

    public function shortLabel(): string {
        return implode(' / ', $this->shortLabelList());
    }

    public function label(): string {
        return implode(' / ', $this->labelList());
    }

    public function unseeded(): bool {
        return $this->info()['Seeders'] === 0;
    }

    /**
     * Get the encoding of this upload
     */
    public function description(): string {
        return $this->info()['Description'];
    }

    /**
     * Get the encoding of this upload. Null for non-music uploads.
     */
    public function encoding(): ?string {
        return $this->info()['Encoding'];
    }

    public function fileTotal(): int {
        return $this->info()['FileCount'];
    }

    /**
     * Get the files of this upload
     */
    public function filelist(): array {
        return $this->info()['FileList'];
    }

    /**
     * Get the format of this upload. Null for non-music uploads.
     */
    public function format(): ?string {
        return $this->info()['Format'];
    }

    public function freeleechStatus(): string {
        return $this->info()['FreeTorrent'];

    }
    /**
     * Group ID this torrent belongs to
     */
    public function groupId(): int {
        return $this->info()['GroupID'];
    }

    /**
     * Get the torrent group in which this torrent belongs.
     */
    public function group(): TGroup {
        if (!isset($this->tgroup)) {
            $this->tgroup = new TGroup($this->info()['GroupID']);
        }
        return $this->tgroup;
    }

    /**
     * It is possible that a torrent can be orphaned from a group, in which case the
     * TGroup property cannot be instantiated, even though the Torrent object can.
     * This method can be used to verify that group() can be called. (See ReportsV2).
     */
    public function hasTGroup(): bool {
        return (new Manager\TGroup)->findById($this->info()['GroupID']) instanceof TGroup;
    }

    public function hasBadFiles(): bool {
        return $this->info()['BadFiles'];
    }

    public function hasBadFolders(): bool {
        return $this->info()['BadFolders'];
    }

    public function hasBadTags(): bool {
        return $this->info()['BadTags'];
    }

    public function hasCassetteApproved(): bool {
        return $this->info()['CassetteApproved'];
    }

    public function hasLossymasterApproved(): bool {
        return $this->info()['LossymasterApproved'];
    }

    public function hasLossywebApproved(): bool {
        return $this->info()['LossywebApproved'];
    }

    public function hasMissingLineage(): bool {
        return $this->info()['MissingLineage'];
    }

    public function isFreeleech(): bool {
        return $this->info()['FreeTorrent'] == '1';
    }

    public function isFreeleechPersonal(): bool {
        return $this->info()['PersonalFL'];
    }

    public function isNeutralleech(): bool {
        return $this->info()['FreeTorrent'] == '2';
    }

    /**
     * Is this a remastered release?
     */
    public function isRemastered(): bool {
        return $this->info()['Remastered'];
    }

    public function isRemasteredUnknown(): bool {
        return $this->isRemastered() && !$this->remasterYear();
    }

    public function isScene(): bool {
        return $this->info()['Scene'];
    }

    public function lastReseedRequest(): ?string {
        return $this->info()['LastReseedRequest'];
    }

    /**
     * Does it have a .cue file?
     */
    public function hasCue(): bool {
        return $this->info()['HasCue'];
    }

    /**
     * Does it have logs?
     */
    public function hasLog(): bool {
        return $this->info()['HasLog'];
    }

    /**
     * Does it have uploaded logs?
     */
    public function hasLogDb(): bool {
        return $this->info()['HasLogDB'];
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
     * The infohash of this torrent (binary)
     *
     * @return string raw infohash
     */
    public function infohashBinary(): string {
        return $this->info()['info_hash_raw'];
    }

    public function lastActiveDate(): ?string {
        return $this->info()['last_action'];
    }

    /**
     * The size (in bytes) of this upload
     */
    public function leecherTotal(): int {
        return $this->info()['Leechers'];
    }

    /**
     * The log score of this torrent
     */
    public function logChecksum(): bool {
        return $this->info()['LogChecksum'];
    }

    /**
     * The log score of this torrent
     */
    public function logScore(): int {
        return $this->info()['LogScore'];
    }

    /**
     * The media of this torrent. Will be null for non-music uploads.
     */
    public function media(): ?string {
        return $this->info()['Media'];
    }

    public function path(): string {
        return $this->info()['FilePath'];
    }

    public function remasterCatalogueNumber(): ?string {
        return $this->info()['RemasterCatalogueNumber'];
    }

    public function remasterRecordLabel(): ?string {
        return $this->info()['RemasterRecordLabel'];
    }

    public function remasterTitle(): ?string {
        return $this->info()['RemasterTitle'];
    }

    public function remasterYear(): ?int {
        return $this->info()['RemasterYear'];
    }

    public function remasterTuple(): string {
        return implode('!!', [
            $this->media(),
            $this->remasterTitle(),
            $this->remasterYear(),
            $this->remasterRecordLabel(),
            $this->remasterCatalogueNumber(),
        ]);
    }

    public function ripLogIdList(): array {
        return $this->info()['ripLogIds'];
    }

    public function seederTotal(): int {
        return $this->info()['Seeders'];
    }

    /**
     * The size (in bytes) of this upload
     */
    public function size(): int {
        return $this->info()['Size'];
    }

    public function snatchTotal(): int {
        return $this->info()['Snatched'];
    }

    public function uploadDate(): string {
        return $this->info()['Time'];
    }

    /**
     * Was it uploaded less than an hour ago? (Request fill grace period)
     */
    public function uploadGracePeriod(): bool {
        return strtotime($this->uploadDate()) > date('U') - 3600;
    }

    /**
     * The uploader ID of this torrent
     */
    public function uploaderId(): int {
        return $this->info()['UserID'];
    }

    /**
     * The uploader of this torrent
     */
    public function uploader(): User {
        return new User($this->uploaderId());
    }

    public function isPerfectFlac(): bool {
        return (bool)self::$db->scalar("
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
        return (bool)self::$db->scalar("
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
     */
    public function torrentFilename(bool $asText, int $MaxLength): string {
        $MaxLength -= strlen($this->id) + 1 + ($asText ? 4 : 8);
        $info = $this->info();
        $group = $this->group();
        $artist = safeFilename($group->artistName());
        if ($info['Year'] ?? 0 > 0) {
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
        self::$cache->deleteMulti(["torrent_group_" . $this->groupId(), "torrents_details_" . $this->groupId()]);
        return self::$db->affected_rows();
    }

    public function adjustLogscore(int $logId, $adjusted, int $adjScore, $adjChecksum, int $adjBy, $adjReason, array $adjDetails): int {
        self::$db->prepared_query("
            UPDATE torrents_logs SET
                Adjusted = ?, AdjustedScore = ?, AdjustedChecksum = ?, AdjustedBy = ?, AdjustmentReason = ?, AdjustmentDetails = ?
            WHERE TorrentID = ? AND LogID = ?
            ", $adjusted, $adjScore, $adjChecksum, $adjBy, $adjReason, serialize($adjDetails),
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

    public function logfileList(): array {
        self::$db->prepared_query('
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
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
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
     * Has the viewing user snatched this torrent? (And do they want to know about it?)
     */
    public function isSnatched(int $userId): bool {
        $buckets = 64;
        $bucketMask = $buckets - 1;
        $bucketId = $this->id & $bucketMask;

        $snatchKey = "users_snatched_" . $userId . "_time";
        if (!$this->snatchBucket) {
            $this->snatchBucket = array_fill(0, $buckets, false);
            $updateTime = self::$cache->get_value($snatchKey);
            if ($updateTime === false) {
                $updateTime = [
                    'last' => 0,
                    'next' => 0
                ];
            }
            $this->updateTime = $updateTime;
        } elseif (isset($this->snatchBucket[$bucketId][$this->id])) {
            return true;
        }

        // Torrent was not found in the previously inspected snatch lists
        $bucket =& $this->snatchBucket[$bucketId];
        if ($bucket === false) {
            $now = time();
            // This bucket hasn't been checked before
            $bucket = self::$cache->get_value($snatchKey, true);
            if ($bucket === false || $now > $this->updateTime['next']) {
                $bucketKeyStem = 'users_snatched_' . $userId . '_';
                $updated = [];
                $qid = self::$db->get_query_id();
                if ($bucket === false || $this->updateTime['last'] == 0) {
                    for ($i = 0; $i < $buckets; $i++) {
                        $this->snatchBucket[$i] = [];
                    }
                    // Not found in cache. Since we don't have a suitable index, it's faster to update everything
                    self::$db->prepared_query("
                        SELECT fid FROM xbt_snatched WHERE uid = ?
                        ", $userId
                    );
                    while ([$id] = self::$db->next_record(MYSQLI_NUM, false)) {
                        $this->snatchBucket[$id & $bucketMask][(int)$id] = true;
                    }
                    $updated = array_fill(0, $buckets, true);
                } elseif (isset($bucket[$this->id])) {
                    // Old cache, but torrent is snatched, so no need to update
                    return true;
                } else {
                    // Old cache, check if torrent has been snatched recently
                    self::$db->prepared_query("
                        SELECT fid FROM xbt_snatched WHERE uid = ? AND tstamp >= ?
                        ", $userId, $this->updateTime['last']
                    );
                    while ([$id] = self::$db->next_record(MYSQLI_NUM, false)) {
                        $bucketId = $id & $bucketMask;
                        if ($this->snatchBucket[$bucketId] === false) {
                            $this->snatchBucket[$bucketId] = self::$cache->get_value("$bucketKeyStem$bucketId", true);
                            if ($this->snatchBucket[$bucketId] === false) {
                                $this->snatchBucket[$bucketId] = [];
                            }
                        }
                        $this->snatchBucket[$bucketId][(int)$id] = true;
                        $updated[$bucketId] = true;
                    }
                }
                self::$db->set_query_id($qid);
                for ($i = 0; $i < $buckets; $i++) {
                    if (isset($updated[$i])) {
                        self::$cache->cache_value("$bucketKeyStem$i", $this->snatchBucket[$i], 7200);
                    }
                }
                $this->updateTime['last'] = $now;
                $this->updateTime['next'] = $now + self::SNATCHED_UPDATE_INTERVAL;
                self::$cache->cache_value($snatchKey, $this->updateTime, 7200);
            }
        }
        return isset($bucket[$this->id]);
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
            'tdate'  => $this->uploadDate(),
        ];

        $userMan   = new Manager\User;
        $groupId   = $this->groupId();
        $name      = $this->group()->displayNameText();
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
        $groupId = $this->groupId();
        self::$cache->deleteMulti([
            "torrent_group_" . $groupId,
            "torrents_details_" . $groupId,
            sprintf(self::CACHE_KEY, $groupId),
            sprintf(TGroup::CACHE_TLIST_KEY, $groupId),
        ]);
        return self::$db->affected_rows();
    }

    public function removeLogDb(): int {
        self::$db->prepared_query('
            DELETE FROM torrents_logs WHERE TorrentID = ?
            ', $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Remove a torrent.
     */
    public function remove(int $userId, string $reason, int $trackerReason = -1): array {
        $qid = self::$db->get_query_id();
        $info = $this->info();
        if ($this->id > MAX_PREV_TORRENT_ID) {
            (new \Gazelle\Bonus($this->uploader()))->removePointsForUpload($this);
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
        self::$cache->decrement('stats_torrent_count');

        $group = $this->group();
        $groupId = $group->id();
        $Count = self::$db->scalar("
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

        if ($userId !== 0) {
            $key = sprintf(self::USER_RECENT_UPLOAD, $userId);
            $RecentUploads = self::$cache->get_value($key);
            if (is_array($RecentUploads)) {
                foreach ($RecentUploads as $Key => $Recent) {
                    if ($Recent['ID'] == $groupId) {
                        $deleteKeys[] = $key;
                        break;
                    }
                }
            }
        }

        $deleteKeys[] = "torrent_download_" . $this->id;
        $deleteKeys[] = "torrent_group_" . $groupId;
        $deleteKeys[] = "torrents_details_" . $groupId;
        self::$cache->deleteMulti($deleteKeys);

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

    public function peerlistTotal() {
        $key = sprintf(self::CACHE_KEY_PEERLIST_TOTAL, $this->id);
        if (($total = self::$cache->get_value($key)) === false) {
            // force flush the first page of results
            self::$cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $this->id, 0));
            $total = self::$db->scalar("
                SELECT count(*)
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $total, 300);
        }
        return $total;
    }

    public function peerlistPage(int $userId, int $limit, int $offset) {
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
            SELECT count(*) FROM users_downloads WHERE TorrentID = ?
            ", $this->id
        );
    }

    public function downloadPage(int $limit, int $offset): array {
        self::$db->prepared_query("
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function snatchPage(int $limit, int $offset): array {
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
