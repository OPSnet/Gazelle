<?php

namespace Gazelle\Enum;

enum NotificationTicketState: string {
    case Pending = 'pending';
    case Stale   = 'stale';
    case Active  = 'active';
    case Error   = 'error';
    case Removed = 'removed';
    case Done    = 'done';
}
