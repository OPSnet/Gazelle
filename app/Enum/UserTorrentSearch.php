<?php

namespace Gazelle\Enum;

enum UserTorrentSearch: string {
    case seeding  = 'seeding';
    case snatched = 'snatched';
    case uploaded = 'uploaded';
}
