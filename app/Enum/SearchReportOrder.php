<?php

namespace Gazelle\Enum;

enum SearchReportOrder: int {
    case createdAsc   = 0;
    case createdDesc  = 1;
    case resolvedAsc  = 2;
    case resolvedDesc = 3;

    public function label(): string {
        return match ($this) {
            self::createdAsc   => 'created-asc',
            self::createdDesc  => 'created-desc',
            self::resolvedAsc  => 'resolved-asc',
            self::resolvedDesc => 'resolved-desc', /** @phpstan-ignore-line */
        };
    }

    public function orderBy(): string {
        return match ($this) {
            self::createdAsc,
            self::createdDesc => 'created',
            self::resolvedAsc,
            self::resolvedDesc => 'resolved', /** @phpstan-ignore-line */
        };
    }

    public function direction(): string {
        return match ($this) {
            self::createdAsc,
            self::resolvedAsc => 'ASC',
            self::createdDesc,
            self::resolvedDesc => 'DESC', /** @phpstan-ignore-line */
        };
    }
}
