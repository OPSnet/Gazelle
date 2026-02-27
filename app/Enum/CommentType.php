<?php

namespace Gazelle\Enum;

/* The values are used to build urls: comments.php?action=collages */

enum CommentType: string {
    case artist   = 'artist';
    case collage  = 'collages';
    case request  = 'requests';
    case tgroup   = 'torrents';
}
