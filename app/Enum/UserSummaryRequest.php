<?php

namespace Gazelle\Enum;

enum UserSummaryRequest: string {
    case bountyTotal  = 'bounty_total';
    case createdTotal = 'created_total';
    case voteTotal    = 'vote_total';
    case bountySize   = 'bounty_size';
    case createdSize  = 'created_size';
    case voteSize     = 'vote_size';
}
