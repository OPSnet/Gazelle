<?php

namespace Gazelle\DB;

use Gazelle\Enum\PgInfoOrderBy;
use Gazelle\Enum\Direction;

class PgInfo {
    use \Gazelle\Pg;

    public function __construct(
        protected PgInfoOrderBy $orderBy = PgInfoOrderBy::tableName,
        protected Direction $direction = Direction::ascending,
    ) {}

    public function info(): array {
        return $this->pg()->all("
            select t.table_schema || '.' || t.table_name as table_name,
                pg_relation_size(t.table_schema || '.' || t.table_name) as table_size,
                pg_indexes_size(t.table_schema || '.' || t.table_name)  as index_size,
                s.n_live_tup as live,
                s.n_dead_tup as dead,
                case when s.n_dead_tup + s.n_live_tup = 0
                    then 0
                    else round(s.n_dead_tup/(s.n_dead_tup + n_live_tup*1.0), 5)
                end as dead_ratio,
                now() - s.last_autoanalyze as analyze_delta,
                now() - s.last_autovacuum  as vacuum_delta,
                s.autoanalyze_count        as analyze_total,
                s.autovacuum_count         as vacuum_total
            from information_schema.tables t
            inner join pg_stat_user_tables s on (
                s.schemaname = t.table_schema and s.relname = t.table_name
            )
            where t.table_schema not in ('information_schema', 'pg_catalog')
            order by {$this->orderBy->value} {$this->direction->value} nulls last
        ");
    }

    public static function lookupOrderby(string $columnName): PgInfoOrderBy {
        return match ($columnName) {
            default                            => PgInfoOrderBy::tableName,
            PgInfoOrderBy::tableSize->value    => PgInfoOrderBy::tableSize,
            PgInfoOrderBy::indexSize->value    => PgInfoOrderBy::indexSize,
            PgInfoOrderBy::live->value         => PgInfoOrderBy::live,
            PgInfoOrderBy::dead->value         => PgInfoOrderBy::dead,
            PgInfoOrderBy::deadRatio->value    => PgInfoOrderBy::deadRatio,
            PgInfoOrderBy::analyzeDelta->value => PgInfoOrderBy::analyzeDelta,
            PgInfoOrderBy::analyzeTotal->value => PgInfoOrderBy::analyzeTotal,
            PgInfoOrderBy::vacuumDelta->value  => PgInfoOrderBy::vacuumDelta,
            PgInfoOrderBy::vacuumTotal->value  => PgInfoOrderBy::vacuumTotal,
        };
    }
}
