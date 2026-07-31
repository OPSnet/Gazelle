<?php

declare(strict_types=1);

namespace Gazelle\DB;

use Gazelle\Enum\Direction;
use Gazelle\Enum\MysqlTableMode;
use Gazelle\Enum\MysqlInfoOrderBy;

class MysqlInfo extends \Gazelle\Base {
    public function __construct(
        protected MysqlTableMode $mode      = MysqlTableMode::all,
        protected MysqlInfoOrderBy $orderBy = MysqlInfoOrderBy::tableName,
        protected Direction $direction      = Direction::ascending,
    ) {}

    public function orderBy(): MysqlInfoOrderBy {
        return $this->orderBy;
    }

    public function info(): array {
        switch ($this->mode) {
            case MysqlTableMode::merged:
                $tableColumn = "replace(table_name, 'deleted_', '')";
                $where = '';
                break;
            case MysqlTableMode::exclude:
                $tableColumn = 'table_name';
                $where = "AND table_name NOT LIKE 'deleted%'";
                break;
            default:
                $tableColumn = 'table_name';
                $where = '';
                break;
        }

        self::$db->prepared_query("
            SELECT t.$tableColumn AS table_name,
                t.ENGINE AS engine,
                t.ROW_FORMAT AS row_format,
                sum(t.TABLE_ROWS) AS table_rows,
                coalesce(sum(ts.ROWS_READ), 0) AS rows_read,
                avg(t.AVG_ROW_LENGTH) AS avg_row_length,
                sum(t.DATA_LENGTH) AS data_length,
                sum(t.INDEX_LENGTH) AS index_length,
                sum(t.INDEX_LENGTH + t.DATA_LENGTH) AS total_length,
                sum(t.DATA_FREE) AS data_free,
                CASE WHEN sum(t.DATA_LENGTH) = 0 THEN 0 ELSE sum(t.DATA_FREE) / sum(t.DATA_LENGTH) END as free_ratio,
                CASE WHEN wsbt.COUNT_READ + wsbt.COUNT_WRITE + wsbt.COUNT_FETCH
                    + wsbt.COUNT_INSERT + wsbt.COUNT_UPDATE + wsbt.COUNT_DELETE = 0
                    THEN 0
                    ELSE
                        (wsbt.COUNT_READ + wsbt.COUNT_WRITE + wsbt.COUNT_FETCH
                            + wsbt.COUNT_INSERT + wsbt.COUNT_UPDATE + wsbt.COUNT_DELETE)
                        / (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Uptime')
                    END AS iops
            FROM information_schema.tables t
            INNER JOIN performance_schema.table_io_waits_summary_by_table wsbt
                ON (wsbt.OBJECT_SCHEMA = t.TABLE_SCHEMA AND wsbt.OBJECT_NAME = t.TABLE_NAME)
            LEFT JOIN information_schema.table_statistics ts
                ON (ts.TABLE_SCHEMA = t.TABLE_SCHEMA AND ts.TABLE_NAME = t.TABLE_NAME)
            WHERE t.table_schema = ? $where
            GROUP BY $tableColumn, engine, row_format
            ORDER BY {$this->orderBy->value} {$this->direction->value}
            ", MYSQL_DB
        );
        return self::$db->to_array('table_name', MYSQLI_ASSOC);
    }

    public static function columnList(): array {
        return [
            MysqlInfoOrderBy::tableName->value
                => ['dbColumn' => MysqlInfoOrderBy::tableName->value,    'defaultSort' => 'asc',  'text' => 'Free Size',  'alt' => 'table name'],
            MysqlInfoOrderBy::tableRows->value
                => ['dbColumn' => MysqlInfoOrderBy::tableRows->value,    'defaultSort' => 'desc', 'text' => 'Rows',       'alt' => 'total rows'],
            MysqlInfoOrderBy::rowsRead->value
                => ['dbColumn' => MysqlInfoOrderBy::rowsRead->value,     'defaultSort' => 'desc', 'text' => 'Rows read',   'alt' => 'rows read'],
            MysqlInfoOrderBy::dataLength->value
                => ['dbColumn' => MysqlInfoOrderBy::dataLength->value,   'defaultSort' => 'desc', 'text' => 'Data Size',  'alt' => 'table size'],
            MysqlInfoOrderBy::indexLength->value
                => ['dbColumn' => MysqlInfoOrderBy::indexLength->value,  'defaultSort' => 'desc', 'text' => 'Index Size', 'alt' => 'index size'],
            MysqlInfoOrderBy::totalLength->value
                => ['dbColumn' => MysqlInfoOrderBy::totalLength->value,  'defaultSort' => 'desc', 'text' => 'Total Size', 'alt' => 'total size'],
            MysqlInfoOrderBy::dataFree->value
                => ['dbColumn' => MysqlInfoOrderBy::dataFree->value,     'defaultSort' => 'desc', 'text' => 'Data Free',  'alt' => 'data free space'],
            MysqlInfoOrderBy::freeRatio->value
                => ['dbColumn' => MysqlInfoOrderBy::freeRatio->value,    'defaultSort' => 'desc', 'text' => 'Bloat %',    'alt' => 'table bloat'],
            MysqlInfoOrderBy::avgRowLength->value
                => ['dbColumn' => MysqlInfoOrderBy::avgRowLength->value, 'defaultSort' => 'desc', 'text' => 'Row Size',   'alt' => 'mean row length'],
            MysqlInfoOrderBy::iops->value
                => ['dbColumn' => MysqlInfoOrderBy::iops->value,         'defaultSort' => 'desc', 'text' => 'IOPS',   'alt' => 'innodb ops sec'],
        ];
    }

    public function headerAlt(): string {
        return self::columnList()[$this->orderBy->value]['alt'];
    }
}
