<?php

namespace Gazelle;

class Riplog extends BaseObject {
    final public const tableName = 'torrents_logs';
    final public const pkName    = 'LogID';

    public function __construct(
        public readonly Torrent|TorrentDeleted $torrent,
        public readonly int                    $id,
    ) {}

    public function flush(): static {
        $this->torrent->flush();
        unset($this->info);
        return $this;
    }

    public function location(): string {
        return "view.php?type=riplog&id={$this->torrent->id}.{$this->id}";
    }

    public function link(): string {
        return "<a href=\"{$this->url()}\">Log #{$this->id}</a>";
    }

    /**
     * Get the metadata of the torrent log
     *
     * @return array of many things
     */
    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = self::$db->rowAssoc("
            SELECT
                Details           AS details,
                FileName          AS filename,
                Score             AS score,
                AdjustedScore     AS score_adjusted,
                AdjustedBy        AS adjuster_user_id,
                AdjustmentReason  AS adjustment_reason,
                AdjustmentDetails AS adjustment_details,
                Ripper            AS ripper,
                RipperVersion     AS ripper_version,
                Language          AS ripper_lang,
                LogcheckerVersion AS checker_version,
                ChecksumState     AS checksum_state,
                Checksum         = '1' AS checksum_ok,
                AdjustedChecksum = '1' AS adjusted_checksum_ok,
                Adjusted         = '1' AS is_adjusted
            FROM torrents_logs
            WHERE TorrentID = ?
                AND LogID = ?
            ", $this->torrent->id, $this->id
        );
        $details = trim($info['details'] ?? '');
        $info['detail_list'] = $details !== '' ? explode("\r\n", $details) : [];
        $info['adjustment_list'] = $info['adjustment_details'] ? unserialize($info['adjustment_details']) : [];
        $this->info = $info;
        return $this->info;
    }

    public function isAdjusted(): bool {
        return $this->info()['is_adjusted'];
    }

    public function adjusterUserId(): int {
        return $this->info()['adjuster_user_id'];
    }

    public function adjustmentList(): array {
        return $this->info()['adjustment_list'];
    }

    public function adjustment(string $key): string {
        return $this->adjustmentList()[$key] ?? '';
    }

    public function adjustmentTrack(string $key): int {
        return isset($this->adjustmentList()['tracks']) && isset($this->adjustmentList()['tracks'][$key])
            ? (int)$this->adjustmentList()['tracks'][$key]
            : 0;
    }

    public function adjustmentReason(): string {
        return $this->info()['adjustment_reason'] ?? '';
    }

    public function filename(): string {
        return $this->info()['filename'];
    }

    public function file(): File\RipLog {
        return new File\RipLog($this->torrent->id, $this->id);
    }

    public function html(): File\RipLogHTML {
        return new File\RipLogHTML($this->torrent->id, $this->id);
    }

    public function report(): array {
        return $this->info()['detail_list'];
    }

    /*** Checksum methods ***/

    public function isChecksumOk(): bool {
        return $this->info()['checksum_ok'];
    }

    public function isAdjustedChecksumOk(): bool {
        return $this->info()['adjusted_checksum_ok'];
    }

    public function isActualChecksumOk(): bool {
        return $this->isAdjusted() ? $this->isAdjustedChecksumOk() : $this->isChecksumOk();
    }

    public function checksumState(): string {
        return $this->info()['checksum_state'] ?? 'checksum_invalid';
    }

    /*** Score methods ***/

    public function score(): ?int {
        return $this->info()['score'];
    }

    public function scoreAdjusted(): int {
        return (int)$this->info()['score_adjusted'];
    }

    /*** ripper methods ***/

    public function checkerVersion(): ?string {
        return $this->info()['checker_version'] === '' ? null : $this->info()['checker_version'];
    }

    public function ripper(): ?string {
        return $this->info()['ripper'] === '' ? null : $this->info()['ripper'];
    }

    public function ripperLanguage(): ?string {
        return is_null($this->ripper()) ? null : $this->info()['ripper_lang'];
    }

    public function ripperVersion(): ?string {
        return $this->info()['ripper_version'];
    }

    /*** housekeeping ***/

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $this->torrent->id, $this->id
        );
        $this->file()->remove();
        $this->html()->remove();
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
