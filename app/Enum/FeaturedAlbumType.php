<?php

namespace Gazelle\Enum;

use \Gazelle\Enum\LeechReason;

enum FeaturedAlbumType: int {
    case AlbumOfTheMonth = 0;
    case Showcase        = 1;

    public function label(): string {
        return match($this) {
            FeaturedAlbumType::AlbumOfTheMonth => 'Album of the Month',
            FeaturedAlbumType::Showcase        => 'Showcase', /** @phpstan-ignore-line */
        };
    }

    public function forumId(): int {
        return match($this) {
            FeaturedAlbumType::AlbumOfTheMonth => AOTM_FORUM_ID,
            FeaturedAlbumType::Showcase        => SHOWCASE_FORUM_ID, /** @phpstan-ignore-line */
        };
    }

    public function leechReason(): LeechReason {
        return match($this) {
            FeaturedAlbumType::AlbumOfTheMonth => LeechReason::AlbumOfTheMonth,
            FeaturedAlbumType::Showcase        => LeechReason::Showcase, /** @phpstan-ignore-line */
        };
    }
}
