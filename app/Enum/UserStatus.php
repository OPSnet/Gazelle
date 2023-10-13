<?php

namespace Gazelle\Enum;

enum UserStatus: string {
    case unconfirmed = '0';
    case enabled     = '1';
    case disabled    = '2';
    case banned      = '3';

    public function label(): string {
        return match ($this) {
            UserStatus::unconfirmed => 'Unconfirmed',
            UserStatus::enabled     => 'Enabled',
            UserStatus::disabled    => 'Disabled',
            UserStatus::banned      => 'Banned', /** @phpstan-ignore-line */
        };
    }
}
