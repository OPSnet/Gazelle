<?php

namespace Gazelle\Enum;

/**
 * This enum determines whether to show all tables,
 * or aggregate the totals of tables and their deleted_ shadows
 * or exclude the deleted_ shadow tables.
 */

enum MysqlTableMode: string {
    case all     = 'all';
    case merged  = 'merged';
    case exclude = 'exclude';
}
