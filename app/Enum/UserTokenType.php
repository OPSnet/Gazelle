<?php

namespace Gazelle\Enum;

enum UserTokenType: string {
    case confirm  = 'confirm';
    case mfa      = 'mfa';
    case password = 'password';

    public function hourInterval(): ?int {
        return match ($this) {
            self::confirm  => 24,
            self::password => 1,
            default        => null, // does not expire
        };
    }
}
