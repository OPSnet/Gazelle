<?php

namespace Gazelle\Enum;

enum UserSummarySeed: string {
    case leeching      = 'leeching';
    case seeding       = 'seeding';
    case snatchTotal   = 'snatch_total';
    case snatchUnique  = 'snatch_unique';
    case bpAccrualHour = 'bp_accrual_hour';
    case seedtimeHour  = 'seedtime_hour';
}
