<?php

namespace Gazelle;

use Gazelle\Enum\LeechReason;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\TorrentFlag;

abstract class TorrentAbstract extends BaseObject {
    final const CACHE_LOCK       = 'torrent_lock_%d';
    final const CACHE_REPORTLIST = 't_rpt2_%d';

    protected TGroup $tgroup;
    protected User   $viewer;

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(Torrent::CACHE_KEY, $this->id),
            sprintf(TorrentDeleted::CACHE_KEY, $this->id),
        ]);
        $this->info = [];
        $this->group()->flush();
        return $this;
    }

    abstract public function addFlag(TorrentFlag $flag, User $user): int;
    abstract public function infoRow(): ?array;
    abstract public function hasToken(int $userId): bool;

    public function link(): string {
        return $this->group()->torrentLink($this->id);
    }

    public function groupLink(): string {
        return $this->group()->link();
    }

    public function fullLink(): string {
        $link = $this->link();
        $edition = $this->edition();
        if ($edition) {
            $link .= " [$edition]";
        }
        $label = $this->label();
        if ($label) {
            $link .= " $label";
        }
        return $link;
    }

    public function name(): string {
        $tgroup = $this->group();
        return $tgroup->categoryName() === 'Music'
            ? $tgroup->artistName() . " – " . $tgroup->name()
            : $tgroup->name();
    }

    public function fullName(): string {
        $name = $this->group()->text();
        $edition = $this->edition();
        if ($edition) {
            $name .= " [$edition]";
        }
        return $name;
    }

    /**
     * Set the viewer context, for snatched indicators etc.
     */
    public function setViewer(User $viewer): static {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf($this->isDeleted() ? TorrentDeleted::CACHE_KEY : Torrent::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->infoRow();
            if (is_null($info)) {
                return $this->info = [];
            }
            foreach (['last_action', 'LastReseedRequest', 'RemasterCatalogueNumber', 'RemasterRecordLabel', 'RemasterTitle', 'RemasterYear'] as $nullable) {
                $info[$nullable] = $info[$nullable] == '' ? null : $info[$nullable];
            }
            foreach (['LogChecksum', 'HasCue', 'HasLog', 'HasLogDB', 'Remastered', 'Scene'] as $zerotruth) {
                $info[$zerotruth] = !($info[$zerotruth] == '0');
            }
            foreach (['BadFiles', 'BadFolders', 'BadTags', 'CassetteApproved', 'LossymasterApproved', 'LossywebApproved', 'MissingLineage'] as $emptytruth) {
                $info[$emptytruth] = !($info[$emptytruth] == '');
            }

            $info['ripLogIds'] = empty($info['ripLogIds']) ? [] : array_map('intval', explode(',', $info['ripLogIds']));
            $info['LogCount'] = count($info['ripLogIds']);
            $info['FileList'] = explode("\n", $info['FileList']);
            if (!$this->isDeleted()) {
                self::$cache->cache_value($key, $info, ($info['Seeders'] ?? 0) > 0 ? 600 : 3600);
            }
        }

        if (!$this->isDeleted() && isset($this->viewer)) {
            $info['PersonalFL'] = $info['FreeTorrent'] == '0' && $this->hasToken($this->viewer->id());
            $info['IsSnatched'] = $this->viewer->snatch()->showSnatch($this);
        } else {
            $info['PersonalFL'] = false;
            $info['IsSnatched'] = false;
        }

        $this->info = $info;
        return $this->info;
    }


    /**
     * Assume a torrent has not been deleted. This function is
     * overridden in TorrentDeleted
     */
    public function isDeleted(): bool {
        return false;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    /**
     * Get the torrent release description.
     */
    public function description(): string {
        return $this->info()['Description'] ?? '';
    }

    /**
     * Generate the edition of the torrent
     */
    public function edition(): string {
        $tgroup = $this->group();
        if ($tgroup->categoryName() !== 'Music') {
            return '';
        }
        if ($this->isRemastered()) {
            $edition = [
                $this->remasterRecordLabel(),
                $this->remasterCatalogueNumber(),
                $this->remasterTitle(),
            ];
        } elseif ($tgroup->recordLabel() || $tgroup->catalogueNumber()) {
            $edition = [
                $tgroup->recordLabel(),
                $tgroup->catalogueNumber(),
            ];
        } else {
            $edition = [
                'Original Release',
            ];
        }
        $edition = implode(' / ', array_filter($edition, fn($e) => !is_null($e)));
        return $this->isRemastered() ? ($this->remasterYear() . " – " . $edition) : $edition;
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
     * Parse a meta filename into a more useful array structure
     *
     * @return array with the keys 'ext', 'size' and 'name'
     */
    protected function filenameParse(string $metaname): array {
        if (preg_match('/^(\..*?) s(\d+)s (.+) (?:&divide;|' . FILELIST_DELIM . ')$/', $metaname, $match)) {
            return [
                'ext'  => $match[1] ?? null,
                'size' => (int)$match[2],
                // transform leading blanks into hard blanks so that it shows up in HTML
                'name' => preg_replace_callback('/^(\s+)/', fn($s) => str_repeat('&nbsp;', strlen($s[1])), $match[3] ?? ''),
            ];
        }
        return [
            'ext'  => null,
            'size' => 0,
            'name' => null,
        ];
    }

    /**
     * Get the files of this upload
     * @return array of ['file', 'ext', 'size'] for each file
     */
    public function fileList(): array {
        return array_map(fn ($f) => $this->filenameParse($f), $this->info()['FileList']);
    }

    /**
     * Aggregate the audio files per audio type
     *
     * @return array of array of [ac3, flac, m4a, mp3] => count
     */
    public function fileListAudioMap(): array {
        $map = [];
        foreach ($this->fileList() as $file) {
            if (is_null($file['ext'])) {
                continue;
            }
            $ext = substr($file['ext'], 1); // skip over period
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
     * Create a string that contains file info in the old format for the API
     *
     * @return string with the format 'NAME{{{SIZE}}}|||NAME{{{SIZE}}}|||...'
     */
    public function fileListLegacyAPI(): string {
        return implode('|||', array_map(
            fn ($file) => $file['name'] . '{{{' . $file['size'] . '}}}',
            $this->fileList()
        ));
    }

    /**
     * Get the format of this upload. Null for non-music uploads.
     */
    public function format(): ?string {
        return $this->info()['Format'];
    }

    public function leechType(): LeechType {
        return match ($this->info()['FreeTorrent']) {
            LeechType::Free->value    => LeechType::Free,
            LeechType::Neutral->value => LeechType::Neutral,
            default                   => LeechType::Normal,
        };
    }

    public function leechReason(): LeechReason {
        return match ($this->info()['FreeLeechType']) {
            LeechReason::AlbumOfTheMonth->value => LeechReason::AlbumOfTheMonth,
            LeechReason::Permanent->value       => LeechReason::Permanent,
            LeechReason::Showcase->value        => LeechReason::Showcase,
            LeechReason::StaffPick->value       => LeechReason::StaffPick,
            default                             => LeechReason::Normal,
        };
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
            $this->tgroup = new TGroup($this->groupId());
            if (isset($this->viewer)) {
                $this->tgroup->setViewer($this->viewer);
            }
        }
        return $this->tgroup;
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
     * It is possible that a torrent can be orphaned from a group, in which case the
     * TGroup property cannot be instantiated, even though the Torrent object can.
     * This method can be used to verify that group() can be called.
     */
    public function hasTGroup(): bool {
        return (new Manager\TGroup)->findById($this->groupId()) instanceof TGroup;
    }

    /**
     * The infohash of this torrent
     */
    public function infohash(): string {
        return $this->info()['info_hash'];
    }

    /**
     * The infohash as expected by Ocelot
     */
    public function infohashEncoded(): string {
        return rawurlencode($this->info()['info_hash_raw']);
    }

    public function isFreeleech(): bool {
        return $this->info()['FreeTorrent'] == LeechType::Free->value;
    }

    public function isFreeleechPersonal(): bool {
        return $this->info()['PersonalFL'];
    }

    public function isNeutralleech(): bool {
        return $this->info()['FreeTorrent'] == LeechType::Neutral->value;
    }

    /* Is this a Perfect Flac?
     * - CD with 100% rip
     * - FLAC from any other media
     */
    public function isPerfectFlac(): bool {
        return $this->format() === 'FLAC'
        && (
            ($this->media() === 'CD' && $this->logScore() === 100)
            ||
            (in_array($this->media(), ['Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT']))
        );
    }

    /* Is this a Perfecter Flac?
     * - `CD with 100% rip
     * - FLAC from DAT or Cassette
     * - 24bit FLAC from any other media
     */
    public function isPerfecterFlac(): bool {
        return $this->format() === 'FLAC'
        && (
            ($this->media() === 'CD' && $this->logScore() === 100)
            ||
            (
                $this->encoding() === '24bit Lossless'
                &&
                (in_array($this->media(), ['Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT']))
            )
            ||
            (in_array($this->media(), ['Cassette', 'DAT']))
        );
    }

    /**
     * Is this a remastered release?
     */
    public function isRemastered(): bool {
        return $this->info()['Remastered'] ?? false;
    }

    public function isRemasteredUnknown(): bool {
        return $this->isRemastered() && !$this->remasterYear();
    }

    public function isScene(): bool {
        return $this->info()['Scene'];
    }

    /**
     * TO BE USED JUDICIOUSLY - SITE CODE SHOULD NEVER CALL THIS
     * Is this being actively seeded *right now*?
     */
    public function isSeedingRealtime(): bool {
        return (bool)self::$db->scalar("
            SELECT 1 FROM xbt_files_users WHERE remaining = 0 and active = 1 and fid = ?
            ", $this->id
        );
    }

    public function lastActiveDate(): ?string {
        return $this->info()['last_action'];
    }

    public function lastReseedRequestDate(): ?string {
        return $this->info()['LastReseedRequest'];
    }

    /**
     * The number of leechers of this torrent
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

    /**
     * Get the reports associated with this torrent
     *
     * @return array of ids of \Gazelle\Torrent\Report
     */
    public function reportIdList(User $viewer): array {
        if ($this->isDeleted()) {
            return [];
        }
        $key = sprintf(self::CACHE_REPORTLIST, $this->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT r.ID      AS id,
                    r.ReporterID AS reporter_id,
                    trc.is_invisible
                FROM reportsv2 r
                INNER JOIN torrent_report_configuration trc ON (trc.type = r.Type)
                WHERE r.Status != 'Resolved'
                    AND r.TorrentID = ?
                ", $this->id
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$db->set_query_id($qid);
            self::$cache->cache_value($key, $list, 7200);
        }
        if (!$viewer->isStaff()) {
            $list = array_filter($list, fn($r) => $r['is_invisible'] == 0 || $r['reporter_id'] == $viewer->id());
        }
        return array_column($list, 'id');
    }

    public function reportTotal(User $viewer): int {
        return count($this->reportIdList($viewer));
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

    /**
     * How many tokens are required to download for free?
     */
    public function tokenCount(): int {
        return (int)ceil($this->size() / BYTES_PER_FREELEECH_TOKEN);
    }

    public function unseeded(): bool {
        return $this->seederTotal() === 0;
    }

    /**
     * Was it uploaded less than an hour ago? (Request fill grace period)
     */
    public function isUploadGracePeriod(): bool {
        return strtotime($this->created()) > date('U') - 3600;
    }

    /**
     * Was it active more then 14 days ago? If never active has it been 3 days? (Reseed grace period)
     */
    public function isReseedRequestAllowed(): bool {
        $lastRequestDate = $this->lastReseedRequestDate();
        $lastActiveDate  = $this->lastActiveDate();
        $lastActiveEpoch = is_null($lastActiveDate) ? 0 : (int)strtotime($lastActiveDate);
        $createdEpoch    = (int)strtotime($this->created());

        return match (true) {
            !$lastActiveEpoch && !$lastRequestDate => (time() >= strtotime(RESEED_NEVER_ACTIVE_TORRENT . ' days', $createdEpoch)),
            !$lastRequestDate                      => (time() >= strtotime(RESEED_TORRENT . 'days', $lastActiveEpoch)),
            default                                => false,
        };
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

    /**** TORRENT FLAG TABLE METHODS ****/

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

    public function hasUploadLock(): bool {
        return (bool)self::$cache->get_value("torrent_{$this->id}_lock");
    }

    public function lockUpload(): void {
        self::$cache->cache_value(sprintf(self::CACHE_LOCK, $this->id), true, 120);
    }

    public function unlockUpload(): void {
        self::$cache->delete_value(sprintf(self::CACHE_LOCK, $this->id));
    }

    /**** LABEL METHODS (e.g. [WEB / FLAC / Lossless]) ****/

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
                if (!$info['HasLogDB']) {
                    $label[] = '<span class="tooltip" style="float: none" title="There is a logifile in the torrent, but it has not been uploaded to the site!">Log</span>';
                } else {
                    if (isset($this->viewer) && $this->viewer->isStaff()) {
                        $label[] = "<a href=\"torrents.php?action=viewlog&torrentid={$this->id}&groupid={$this->groupId()}\">Log ({$info['LogScore']}%)</a>";
                    } else {
                        $label[] = "Log ({$info['LogScore']}%)";
                    }
                }
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

    public function shortLabel(): string {
        return implode(' / ', $this->shortLabelList());
    }

    public function shortLabelLink(): string {
        $short = $this->shortLabel();
        if (!$short) {
            return '';
        }
        // deal with nested log link
        // we have "....<a href="x">....</a>...."
        // we want "<a href="y">....</a><a href="x">....</a><a href="y">....</a>"
        if (str_contains($short, '<a href=')) {
            $short = preg_replace('#(<a href=.*</a>)#', "</a>\\1<a href=\"{$this->url()}\">", $short);
        }
        return "<a href=\"{$this->url()}\">[$short]</a>";
    }

    public function labelList(?User $viewer = null): array {
        $info = $this->info();
        $extra = [];
        if ($viewer?->snatch()?->showSnatch($this)) {
            $extra[] = $this->labelElement('tl_snatched', 'Snatched!');
        }
        if ($info['PersonalFL']) {
            $extra[] = $this->labelElement('tl_free tl_personal', 'Personal Freeleech!');
        } else {
            $leechType = $this->leechType();
            if ($leechType == LeechType::Free) {
                $extra[] = $this->labelElement('tl_free', 'Freeleech!');
            } elseif ($leechType == LeechType::Neutral) {
                $extra[] = $this->labelElement('tl_free tl_neutral', 'Neutral Leech!');
            }
        }
        if ($info['Media'] === 'CD' && $info['HasLog'] && $info['HasLogDB'] && !$info['LogChecksum']) {
            $extra[] = $this->labelElement('tl_notice', 'Bad/Missing Checksum');
        }
        if ($this->hasBadTags()) {
            $extra[] = $this->labelElement('tl_reported tl_bad_tags', 'Bad Tags');
        }
        if ($this->hasBadFolders()) {
            $extra[] = $this->labelElement('tl_reported tl_bad_folders', 'Bad Folders');
        }
        if ($this->hasBadFiles()) {
            $extra[] = $this->labelElement('tl_reported tl_bad_filenames', 'Bad File Names');
        }
        if ($this->hasMissingLineage()) {
            $extra[] = $this->labelElement('tl_reported tl_missing_lineage', 'Missing Lineage');
        }
        if ($this->hasCassetteApproved()) {
            $extra[] = $this->labelElement('tl_approved tl_cassette', 'Cassette Approved');
        }
        if ($this->hasLossymasterApproved()) {
            $extra[] = $this->labelElement('tl_approved tl_lossy_master', 'Lossy Master Approved');
        }
        if ($this->hasLossywebApproved()) {
            $extra[] = $this->labelElement('tl_approved tl_lossy_web', 'Lossy WEB Approved');
        }
        if ($viewer && $this->reportTotal($viewer)) {
            $extra[] = $this->labelElement('tl_reported', 'Reported');
        }
        return $extra;
    }

    public function label(?User $viewer = null): string {
        $short = $this->shortLabel();
        $extra = $this->labelList($viewer);
        if ($short) {
            return "[$short]" . ($extra ? ' ' . implode(' / ', $extra) : '');
        } elseif ($extra) {
            return implode(' / ', $extra);
        }
        return '';
    }
}
