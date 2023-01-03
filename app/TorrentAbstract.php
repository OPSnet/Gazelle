<?php

namespace Gazelle;

abstract class TorrentAbstract extends BaseObject {
    const CACHE_KEY = 't_%d';

    protected array  $info;
    protected TGroup $tgroup;
    protected User   $viewer;

    public function flush(): TorrentAbstract {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
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
        $link = $this->groupLink();
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

    public function fullName(): string {
        $name = $this->group()->text();
        $edition = $this->edition();
        if ($edition) {
            $name .= " [$edition]";
        }
        return $name;
    }

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = $this->infoRow();
            if (is_null($info)) {
                return $this->info = [];
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
            if (!$this->isDeleted()) {
                $info['Reported'] = self::$db->scalar("
                    SELECT count(*)
                    FROM reportsv2 r
                    WHERE r.Status != 'Resolved'
                        AND r.TorrentID = ?
                    ", $this->id
                );
                self::$cache->cache_value($key, $info, ($info['Seeders'] ?? 0) > 0 ? 600 : 3600);
            }
        }

        if (!$this->isDeleted() && isset($this->viewer)) {
            $info['PersonalFL'] = $info['FreeTorrent'] == '0' && $this->hasToken($this->viewer->id());
            $info['IsSnatched'] = (new User\Snatch($this->viewer))->showSnatch($this->id);
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
        return $this->info()['Time'];
    }

    /**
     * Get the encoding of this upload
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
                $this->remasterRecordLabel() ?? $tgroup->recordLabel(),
                $this->remasterCatalogueNumber() ?? $tgroup->catalogueNumber(),
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
                'size' => (int)$match[2] ?? 0,
                // transform leading blanks into hard blanks so that it shows up in HTML
                'name' => preg_replace_callback('/^(\s+)/', function ($s) { return str_repeat('&nbsp;', strlen($s[1])); }, $match[3] ?? ''),
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

    public function freeleechStatus(): string {
        return $this->info()['FreeTorrent'];
    }

    public function freeleechType(): string {
        return $this->info()['FreeLeechType'];
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

    public function isFreeleech(): bool {
        return $this->info()['FreeTorrent'] == '1';
    }

    public function isFreeleechPersonal(): bool {
        return $this->info()['PersonalFL'];
    }

    public function isNeutralleech(): bool {
        return $this->info()['FreeTorrent'] == '2';
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

    public function lastActiveDate(): ?string {
        return $this->info()['last_action'];
    }

    public function lastActiveEpoch(): int {
        return strtotime($this->lastActiveDate() ?? 0);
    }

    public function lastReseedRequest(): ?string {
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

    public function reportTotal(): int {
        return $this->info()['Reported'];
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
    public function uploadGracePeriod(): bool {
        return strtotime($this->created()) > date('U') - 3600;
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
        return $short ? "<a href=\"{$this->url()}\">[$short]</a>" : '';
    }

    public function labelList(): array {
        $info = $this->info();
        $extra = [];
        if (isset($this->viewer) && (new User\Snatch($this->viewer))->showSnatch($this->id)) {
            $extra[] = $this->labelElement('tl_snatched', 'Snatched!');
        }
        if (isset($info['FreeTorrent'])) {
            if ($info['FreeTorrent'] == '1') {
                $extra[] = $this->labelElement('tl_free', 'Freeleech!');
            } elseif ($info['FreeTorrent'] == '2') {
                $extra[] = $this->labelElement('tl_free tl_neutral', 'Neutral Leech!');
            }
        } elseif ($info['PersonalFL']) {
            $extra[] = $this->labelElement('tl_free tl_personal', 'Personal Freeleech!');
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
        if (isset($info['Reported']) && $info['Reported']) {
            $extra[] = $this->labelElement('tl_reported', 'Reported');
        }
        return $extra;
    }

    public function label(): string {
        $short = $this->shortLabel();
        $extra = $this->labelList();
        if ($short) {
            return "[$short]" . ($extra ? ' ' . implode(' / ', $extra) : '');
        } elseif ($extra) {
            return implode(' / ', $extra);
        }
        return '';
    }
}
