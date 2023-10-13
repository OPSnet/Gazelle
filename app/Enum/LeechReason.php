<?php

namespace Gazelle\Enum;

enum LeechReason: string {
    case Normal          = '0';
    case StaffPick       = '1';
    case Permanent       = '2';
    case Showcase        = '3';
    case AlbumOfTheMonth = '4';

    public function label(): string {
        return match ($this) {
            LeechReason::Normal          => 'Normal',
            LeechReason::StaffPick       => 'Staff Pick',
            LeechReason::Permanent       => 'Permanent FL',
            LeechReason::Showcase        => 'Showcase',
            LeechReason::AlbumOfTheMonth => 'Album of the Month', /** @phpstan-ignore-line */
        };
    }
};
