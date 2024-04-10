<?php

namespace Gazelle\Enum;

enum ReportAutoState: string {
    case open        = 'open';
    case closed      = 'closed';
    case in_progress = 'in_progress';
}
