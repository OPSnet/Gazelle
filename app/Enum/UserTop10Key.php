<?php

namespace Gazelle\Enum;

enum UserTop10Key: string {
    case uploader      = 'uploaded';
    case downloader    = 'downloaded';
    case uploadTotal   = 'num_uploads';
    case requestVote   = 'request_votes';
    case requestFill   = 'request_fills';
    case uploadSpeed   = 'up_speed';
    case downloadSpeed = 'down_speed';
}
