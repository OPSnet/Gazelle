<?php

namespace Gazelle;

class TorrentLog extends BaseObject {

    protected Torrent $torrent;
    protected array $info;

    public function __construct(Torrent $torrent, int $id) {
        parent::__construct($id);
        $this->torrent = $torrent;
    }

    public function torrentId(): int {
        return $this->torrent->id();
    }

    public function tableName(): string {
        return 'torrents_logs';
    }

    public function flush() {
        return $this->torrent->flush();
    }

    public function location(): string {
        return $this->torrent->location();
    }

    public function url(): string {
        return $this->torrent->url();
    }

    public function link(): string {
        return $this->torrent->link();
    }

    /**
     * Get the metadata of the torrent log
     *
     * @return array of many things
     */
    public function info(): array {
        if (empty($this->info)) {
            $info = self::$db->rowAssoc("
                SELECT FileName,
                    Details,
                    Adjusted         = '1' AS is_adjusted,
                    Score,
                    AdjustedScore,
                    Checksum         = '1' AS checksum_ok,
                    AdjustedChecksum = '1' AS adjusted_checksum_ok,
                    AdjustedBy,
                    AdjustmentReason,
                    AdjustmentDetails
                FROM torrents_logs
                WHERE TorrentID = ?
                    AND LogID = ?
                ", $this->torrent->id(), $this->id
            ) ?? [];
            $info['detail_list'] = explode("\r\n", $info['Details'] ?? "\r\n");
            $info['adjustment_list'] = $info['AdjustmentDetails'] ? unserialize($info['AdjustmentDetails']) : [];
            $this->info = $info;
        }
        return $this->info;
    }

    public function isAdjusted(): bool {
        return $this->info()['is_adjusted'];
    }

    public function adjustedByUserId(): int {
        return (int)$this->info()['AdjustedBy'];
    }

    public function adjustment(string $key): string {
        return isset($this->info()['adjustment_list'][$key]) ? $this->info()['adjustment_list'][$key] : '';
    }

    public function adjustmentTrack(string $key): int {
        return isset($this->info()['adjustment_list']['tracks']) && isset($this->info()['adjustment_list']['tracks'][$key])
            ? (int)$this->info()['adjustment_list']['tracks'][$key]
            : 0;
    }

    public function adjustmentReason(): string {
        return $this->info()['AdjustmentReason'] ?? '';
    }

    public function filename(): string {
        return $this->info()['FileName'];
    }

    public function report(): string {
        return $this->info()['Details'];
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

    /*** Score methods ***/

    public function score(): ?int {
        return $this->info()['Score'];
    }

    public function adjustedScore(): int {
        return (int)$this->info()['AdjustedScore'];
    }

    public function actualScore(): int {
        return $this->isAdjusted() ? $this->adjustedScore() : $this->score();
    }
}
