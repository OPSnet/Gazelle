<?php

namespace Gazelle\Torrent;

enum ReaperState: string {
    case NEVER    = 'never';
    case UNSEEDED = 'unseeded';

    public function notifyAttr(): string {
        return match ($this) {
            ReaperState::NEVER    => 'no-pm-unseeded-upload',
            ReaperState::UNSEEDED => 'no-pm-unseeded-snatch', /** @phpstan-ignore-line */
        };
    }
}
