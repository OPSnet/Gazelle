<?php

namespace Gazelle\Enum;

enum MysqlInfoOrderBy: string {
    // Note: these names must correspond to the columns defined in Gazelle\DB\MysqlInfo::info()
    case tableName    = 'table_name';
    case tableRows    = 'table_rows';
    case dataLength   = 'data_length';
    case indexLength  = 'index_length';
    case totalLength  = 'total_length';
    case dataFree     = 'data_free';
    case freeRatio    = 'free_ratio';
    case avgRowLength = 'avg_row_length';
}
