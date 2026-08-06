<?php

namespace Gazelle\Enum;

use Gazelle\User;

enum UserTorrentSearch: string {
    case downloaded       = 'downloaded';
    case leeching         = 'leeching';
    case seeding          = 'seeding';
    case snatched         = 'snatched';
    case snatchedUnseeded = 'snatched-unseeded';
    case uploaded         = 'uploaded';
    case uploadedUnseeded = 'uploaded-unseeded';

    public function searcher(User $user, User $viewer): \Gazelle\Search\UserTorrent\AbstractSearch {
        return match ($this) {
            self::downloaded       => new \Gazelle\Search\UserTorrent\Downloaded($user, $viewer),
            self::leeching         => new \Gazelle\Search\UserTorrent\Leeching($user, $viewer),
            self::seeding          => new \Gazelle\Search\UserTorrent\Seeding($user, $viewer),
            self::snatched         => new \Gazelle\Search\UserTorrent\Snatched($user, $viewer),
            self::snatchedUnseeded => new \Gazelle\Search\UserTorrent\SnatchedUnseeded($user, $viewer),
            self::uploaded         => new \Gazelle\Search\UserTorrent\Uploaded($user, $viewer),
            self::uploadedUnseeded => new \Gazelle\Search\UserTorrent\UploadedUnseeded($user, $viewer),
        };
    }
}
