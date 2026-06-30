<?php

namespace Gazelle\Enum;

enum UserSummaryTGroup: string {
    case artistAdded    = 'artist_added';
    case downloadTotal  = 'download_total';
    case downloadUnique = 'download_unique';
    case perfectFlac    = 'perfect_flac';
    case perfecterFlac  = 'perfecter_flac';
    case uniqueGroup    = 'unique_group';
    case upload         = 'upload';
}
