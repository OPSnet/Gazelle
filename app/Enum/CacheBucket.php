<?php

namespace Gazelle\Enum;

enum CacheBucket: string {
    case standard = 'i';
    case forum    = 'f';
    case tgroup   = 't';
}
