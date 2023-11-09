<?php

namespace Gazelle\Enum;

enum CollageType: int {
    case personal    = 0;
    case theme       = 1;
    case genre       = 2;
    case discography = 3;
    case label       = 4;
    case staffPick   = 5;
    case chart       = 6;
    case artist      = 7;
    case award       = 8;
    case series      = 9;

    public function label(): string {
        return match ($this) {
            self::artist      => 'Artists',
            self::award       => 'Awards',
            self::chart       => 'Charts',
            self::discography => 'Discography',
            self::genre       => 'Genre Introduction',
            self::label       => 'Label',
            self::personal    => 'Personal',
            self::series      => 'Series',
            self::staffPick   => 'Staff picks',
            self::theme       => 'Theme', /** @phpstan-ignore-line */
        };
    }
}
