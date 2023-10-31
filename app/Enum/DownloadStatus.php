<?php

namespace Gazelle\Enum;

enum DownloadStatus: int {
    case ok        =  1;
    case flood     = -1;
    case free      = -2;
    case no_tokens = -3;
    case ratio     = -4;
    case too_big   = -5;
    case tracker   = -6;

    public function message(): string {
        return match ($this) {
            self::flood     => 'Rate limiting hit on downloading',
            self::free      => 'You cannot use tokens here (already freeleech)',
            self::no_tokens => 'You do not have enough freeleech tokens. Please use the regular DL link.',
            self::ratio     => 'You cannot download while you are on ratio watch',
            self::too_big   => 'This torrent is too large. Please use the regular DL link.',
            self::tracker   => 'Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.',
            default         => '',
        };
    }
}
