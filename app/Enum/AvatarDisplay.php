<?php

namespace Gazelle\Enum;

enum AvatarDisplay: int {
    case show              = 0;
    case none              = 1;
    case fallbackSynthetic = 2;
    case forceSynthetic    = 3;

    public function label(): string {
        return match ($this) {
            AvatarDisplay::show              => 'show',
            AvatarDisplay::none              => 'none',
            AvatarDisplay::fallbackSynthetic => 'fallbackSynthetic',
            AvatarDisplay::forceSynthetic    => 'forceSynthetic', /** @phpstan-ignore-line */
        };
    }
}
