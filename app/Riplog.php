<?php

declare(strict_types=1);

namespace Gazelle;

class Riplog extends BasePgObject {
    final public const tableName = 'riplog';
    final public const pkName    = 'id_riplog';

    public function __construct(
        public readonly Torrent|TorrentDeleted $torrent,
        public readonly int                    $id,
    ) {
        $this->pg()->registerEnum(Enum\RiplogChecksum::class, 'riplog_checksum_t');
    }

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
        return $this->info ??= $this->pg()->rowAssocex('
            select
                r.filename,
                r.score,
                ra.score                 as score_adjusted,
                r.checksum,
                ra.checksum              as checksum_adjusted,
                r.details                as detail_list,
                r.ripper,
                r.ripper_version,
                r.riplog_language,
                r.logchecker_version,
                ra.id_user               as adjuster_id_user,
                ra.reason                as adjustment_reason,
                ra.details               as adjustment_list,
                ra.id_riplog is not null as is_adjusted
            from riplog r
            left join riplog_adjust ra using (id_riplog)
            where r.id_torrent  = $1
                and r.id_riplog = $2
            ', $this->torrent->id, $this->id
        );
    }

    public function isAdjusted(): bool {
        return $this->info()['is_adjusted'];
    }

    public function adjusterUserId(): int|null {
        return $this->info()['adjuster_id_user'];
    }

    public function adjustmentList(): array {
        return $this->info()['adjustment_list'] ?? [];
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

    public function checksum(): Enum\RiplogChecksum {
        return $this->info()['checksum'];
    }

    public function checksumAdjusted(): Enum\RiplogChecksum|null {
        return $this->info()['checksum_adjusted'];
    }

    public function isChecksumOk(): bool {
        return $this->checksum() === Enum\RiplogChecksum::ok;
    }

    public function isAdjustedChecksumOk(): bool {
        return $this->checksumAdjusted() === Enum\RiplogChecksum::ok;
    }

    public function isActualChecksumOk(): bool {
        return $this->isAdjusted() ? $this->isAdjustedChecksumOk() : $this->isChecksumOk();
    }

    /*** Score methods ***/

    public function score(): int {
        return $this->info()['score'];
    }

    public function scoreAdjusted(): int|null {
        return $this->info()['score_adjusted'];
    }

    /*** ripper methods ***/

    public function checkerVersion(): string {
        return $this->info()['logchecker_version'];
    }

    public function ripper(): string|null {
        return $this->info()['ripper'];
    }

    public function ripperLanguage(): string {
        return $this->info()['riplog_language'];
    }

    public function ripperVersion(): string|null {
        return $this->info()['ripper_version'];
    }

    /*** housekeeping ***/

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        /* Any field of the form <column>_adjust is destined
         * for the riplog_adjust table. The <column> part is
         * extracted, and a merge statement is synthesized to
         * handle the update or insert. There is no provision
         * for deleting a riplog_adjust row here: that is
         * handled elsewhere, e.g. when rescoring a riplog.
         * The remaining columns (if any) are forwarded to
         * BasePgObject::modify().
         */
        $adjust = array_map(
            fn ($v) => $v,
            array_filter(
                $this->updateField,
                fn ($k) => str_ends_with((string)$k, '_adjust'),
                ARRAY_FILTER_USE_KEY
            )
        );
        $adjust = array_combine(
            array_map(
                fn ($k) => str_replace('_adjust', '', (string)$k),
                array_keys($adjust)
                ),
            array_values($adjust)
        );
        $this->pg()->begin();
        $modified = false;
        if ($adjust !== []) {
            $column      = [];
            $insert      = [];
            $update      = [];
            $args        = [];
            foreach ($adjust as $field => $value) {
                // nb: placeholders start from $2
                $ph = '$' . (count($args) + 2);
                $column[] = $field;
                $insert[] = $ph;
                $update[] = "$field = $ph";
                $args[]   = $field === 'details'
                    ? $this->pg()->arrayConverter()->output(array_values($value))
                    : $value;
            }
            $sql = "merge into riplog_adjust as target
                using (select $1::int as id_riplog) as source
                on target.id_riplog = source.id_riplog
                when matched then
                    update set " . implode(',', $update) . "
                when not matched then
                    insert (id_riplog, " . implode(',', $column)
                . ") values (\$1, " . implode(',', $insert)
                . ")";
            $modified = (bool)$this->pg()->query($sql, $this->id, ...$args);
        }
        $this->updateField = array_filter(
            $this->updateField,
            fn ($k) => !str_ends_with((string)$k, '_adjust'),
            ARRAY_FILTER_USE_KEY,
        );
        if ($this->updateField === []) {
            unset($this->updateField);
        } else {
            $modified = $modified ?: parent::modify();
        }
        $this->pg()->commit();
        return $modified;
    }

    public function remove(): int {
        $affected = $this->pg()->query('
            delete from riplog where id_riplog = $1 and id_torrent = $2
            ', $this->id, $this->torrent->id
        );
        $this->file()->remove();
        $this->html()->remove();
        $this->flush();
        return $affected;
    }
}
