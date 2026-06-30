<?php

namespace Gazelle\Enum;

enum UserSummary: string {
    case fltoken = 'user_summary_fltoken';
    case misc    = 'user_summary_misc';
    case request = 'user_summary_request';
    case seed    = 'user_summary_seed';
    case tgroup  = 'user_summary_tgroup';
}
