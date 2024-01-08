<?php

namespace Gazelle;

class RipLog extends BaseObject {
    final public const tableName = 'torrents_logs';

    public function flush(): static { return $this; }
    public function location(): string { return 'view.php?type=riplog&id=' . $this->torrentId . '.' . $this->id; }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), "Log #" . $this->id); }

    public function __construct(
        protected int $torrentId,
        protected int $logId,
    ) {
        parent::__construct($logId);
    }

    public function info(): array {
        return $this->info ??= self::$db->rowAssoc("
            SELECT 
                tl.LogID             AS log_id,
                tl.TorrentID         AS torrent_id,
                tl.Score             AS score,
                CASE WHEN tl.Adjusted = '1' THEN tl.AdjustedScore ELSE tl.Score END AS score_adjusted,
                CASE WHEN tl.Checksum = '1' THEN 1 ELSE 0 END AS checksum,
                CASE WHEN tl.AdjustedChecksum = '1' THEN 1 ELSE 0 END AS checksum_adjusted,
                tl.ChecksumState     AS checksum_state,
                CASE WHEN tl.Adjusted = '1' THEN 1 ELSE 0 END AS adjusted,
                um.Username          AS adjusted_by,
                tl.AdjustmentReason  AS adjustment_reason,
                tl.Ripper            AS ripper,
                tl.RipperVersion     AS ripper_version,
                tl.Language          AS ripper_lang,
                tl.LogcheckerVersion AS checker_version
            FROM torrents_logs tl
            LEFT JOIN users_main um ON (um.ID = tl.AdjustedBy)
            WHERE tl.TorrentID = ?
                AND tl.LogID = ?
            ", $this->torrentId, $this->id
        );
    }

    public function logId(): int {
        return $this->info()['log_id'];
    }

    public function torrentId(): int {
        return $this->info()['torrent_id'];
    }

    public function score(): ?int {
        return $this->info()['score'];
    }

    public function scoreAdjusted(): ?int {
        return $this->info()['score_adjusted'];
    }

    public function checksum(): bool {
        return $this->info()['checksum'] ?? false;
    }

    public function checksumAdjusted(): bool {
        return $this->info()['checksum_adjusted'] ?? false;
    }

    public function checksumState(): string {
        return $this->info()['checksum_state'] ?? 'checksum_invalid';
    }

    public function adjusted(): bool {
        return $this->info()['adjusted'] ?? false;
    }

    public function adjustedBy(): ?string {
        return $this->info()['adjusted_by'];
    }

    public function adjustmentReason(): ?string {
        return $this->info()['adjustment_reason'];
    }

    public function ripper(): ?string {
        return $this->info()['ripper'] === '' ? null : $this->info()['ripper'];
    }

    public function ripperLang(): ?string {
        return is_null($this->ripper()) ? null : $this->info()['ripper_lang'];
    }

    public function ripperVersion(): ?string {
        return $this->info()['ripper_version'];
    }

    public function checkerVersion(): ?string {
        return $this->info()['checker_version'] === '' ? null : $this->info()['checker_version'];
    }
}
