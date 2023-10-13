<?php

namespace Gazelle\Enum;

enum AvatarSynthetic: int {
    case identicon = 0;
    case monster   = 1;
    case wavatar   = 2;
    case retro     = 3;
    case robot1    = 4;
    case robot2    = 5;
    case robot3    = 6;

    public function label(): string {
        return match ($this) {
            AvatarSynthetic::identicon => 'Identicon',
            AvatarSynthetic::monster   => 'Monsters',
            AvatarSynthetic::wavatar   => 'Wavatar',
            AvatarSynthetic::retro     => 'Retro',
            AvatarSynthetic::robot1    => 'Robots 1',
            AvatarSynthetic::robot2    => 'Robots 2',
            AvatarSynthetic::robot3    => 'Robots 3', /** @phpstan-ignore-line */
        };
    }
}
