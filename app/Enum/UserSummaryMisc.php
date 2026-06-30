<?php

namespace Gazelle\Enum;

enum UserSummaryMisc: string {
    case collageCreated = 'collage_created';
    case collageContrib = 'collage_contrib';
    case forumPost      = 'forum_post';
    case forumThread    = 'forum_thread';
    case invited        = 'invited';
}
