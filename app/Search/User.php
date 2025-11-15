<?php

declare(strict_types=1);

namespace Gazelle\Search;

class User {
    public function __construct(
        protected string $mode,
    ) {}

    public function matchField(string $field): string {
        return match ($this->mode) {
            'regexp' => "$field ~* ?",
            'strict' => "$field = ?",
            default  => "$field ~~* concat('%', ?::text, '%')",
        };
    }

    public function leftMatch(string $field): string {
        return match ($this->mode) {
            'regexp' => "$field ~* ?",
            'strict' => "$field = ?",
            default  => "$field ~~* concat(?::text, '%')",
        };
    }

    public function op(string $field, string $compare): string {
        return match ($compare) {
            'above'           => "$field > ?",
            'below'           => "$field < ?",
            'between'         => "$field BETWEEN ? AND ?",
            'isnotnull'       => "$field IS NOT NULL",
            'isnull'          => "$field IS NULL",
            'no', 'not_equal' => "$field != ?",
            'subnet'          => "$field <<= ?",
            default           => "$field = ?",
        };
    }

    public function date(string $field, string $compare): string {
        return match ($compare) {
            'after'   => "$field > ? + '1 DAY'::interval",
            'before'  => "$field < ?",
            'between' => "$field BETWEEN ? AND ? + '1 DAY'::interval",
            default   => "$field >= ? AND $field < ? + '1 DAY'::interval",
        };
    }
}
