<?php

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
            SELECT $tableColumn AS table_name,
                ENGINE AS engine,
                sum(TABLE_ROWS) AS table_rows,
                avg(AVG_ROW_LENGTH) AS avg_row_length,
                sum(DATA_LENGTH) AS data_length,
                sum(INDEX_LENGTH) AS index_length,
                sum(INDEX_LENGTH + DATA_LENGTH) AS total_length,
                sum(DATA_FREE) AS data_free,
                CASE WHEN sum(DATA_LENGTH) = 0 THEN 0 ELSE sum(DATA_FREE) / sum(DATA_LENGTH) END as free_ratio
            FROM information_schema.tables
            WHERE table_schema = ? $where
            GROUP BY $tableColumn, engine
            ORDER BY {$this->orderBy->value} {$this->direction->value}
            ", SQLDB
        );
        return self::$db->to_array('table_name', MYSQLI_ASSOC, false);
    }

    public static function columnList(): array {
        return [
            MysqlInfoOrderBy::tableName->value
                => ['dbColumn' => MysqlInfoOrderBy::tableName->value,    'defaultSort' => 'asc',  'text' => 'Free Size',  'alt' => 'table name'],
            MysqlInfoOrderBy::tableRows->value
                => ['dbColumn' => MysqlInfoOrderBy::tableRows->value,    'defaultSort' => 'desc', 'text' => 'Rows',       'alt' => 'total rows'],
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
        ];
    }

    public function headerAlt(): string {
        return self::columnList()[$this->orderBy->value]['alt'];
    }

    public static function lookupOrderby(string $columnName): MysqlInfoOrderBy {
        return match ($columnName) {
            default                               => MysqlInfoOrderBy::tableName,
            MysqlInfoOrderBy::tableRows->value    => MysqlInfoOrderBy::tableRows,
            MysqlInfoOrderBy::dataLength->value   => MysqlInfoOrderBy::dataLength,
            MysqlInfoOrderBy::indexLength->value  => MysqlInfoOrderBy::indexLength,
            MysqlInfoOrderBy::totalLength->value  => MysqlInfoOrderBy::totalLength,
            MysqlInfoOrderBy::dataFree->value     => MysqlInfoOrderBy::dataFree,
            MysqlInfoOrderBy::freeRatio->value    => MysqlInfoOrderBy::freeRatio,
            MysqlInfoOrderBy::avgRowLength->value => MysqlInfoOrderBy::avgRowLength,
        };
    }

    public static function lookupTableMode(string $mode): MysqlTableMode {
        return match ($mode) {
            default                        => MysqlTableMode::all,
            MysqlTableMode::merged->value  => MysqlTableMode::merged,
            MysqlTableMode::exclude->value => MysqlTableMode::exclude,
        };
    }
}
