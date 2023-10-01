<?php

namespace Gazelle\Enum;

enum LeechType: string {
    case Normal  = '0';
    case Free    = '1';
    case Neutral = '2';

    public function label(): string {
        return match($this) {
            LeechType::Normal  => 'Normal',
            LeechType::Neutral => 'Neutral Leech',
            LeechType::Free    => 'Freeleech', /** @phpstan-ignore-line */
        };
    }
};
