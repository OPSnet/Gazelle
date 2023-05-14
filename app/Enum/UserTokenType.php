<?php

namespace Gazelle\Enum;

enum UserTokenType: string {
    case confirm  = 'confirm';
    case mfa      = 'mfa';
    case password = 'password';

    public function interval(): ?string {
        return match($this) {
            self::confirm  => '1 day',
            self::password => '1 hour',
            default        => null, // does not expire
        };
    }
}
