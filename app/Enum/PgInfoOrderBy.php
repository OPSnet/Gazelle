<?php

namespace Gazelle\Enum;

enum PgInfoOrderBy: string {
    // Note: these names must correspond to the columns defined in Gazelle\DB\PgInfo::info()
    case tableName    = 'table_name';
    case tableSize    = 'table_size';
    case indexSize    = 'index_size';
    case live         = 'live';
    case dead         = 'dead';
    case deadRatio    = 'dead_ratio';
    case analyzeDelta = 'analyze_delta';
    case analyzeTotal = 'analyze_total';
    case vacuumDelta  = 'vacuum_delta';
    case vacuumTotal  = 'vacuum_total';
}
