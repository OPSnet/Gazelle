<?php

declare(strict_types=1);

namespace Gazelle\DB;

class PgTable extends AbstractTable {
    public function location(): string {
        return "tools.php?action=db-pg&table={$this->name}";
    }

    protected function schemaTable(): array {
        /* If we have 'schemaname.tablename', split them apart and
         * return them separately. Otherwise we have only a table
         * name, which is assumed to be in the public schema.
         */
        $all = explode('.', $this->name, 2);
        if (count($all) === 2) {
            return $all;
        }
        return ['public', $this->name];
    }

    public function exists(): bool {
        [$schema, $table] = $this->schemaTable();
        return (bool)$this->pg()->scalarex("
            SELECT 1
            FROM information_schema.tables t
            WHERE t.table_schema = $1
                AND t.table_name = $2
            ", $schema, $table
        );
    }

    public function definition(): string {
        [$schema, $table] = $this->schemaTable();
        /* Generating a create table in Postgresql is non-trivial
         * unless you have a pg_dump binary handy (and wish to
         * spawn a child process). So we use some code a person
         * on the internet wrote, and bend it to our needs.
         */
        return implode(
            "\n",
            $this->pgro()->column("
                with pkey as (
                    select cc.conrelid,
                        format(E',\n  constraint %I primary key(%s)', cc.conname,
                                string_agg(a.attname, ', '
                                    order by array_position(cc.conkey, a.attnum))
                        ) pkey
                    from pg_catalog.pg_constraint cc
                    inner join pg_catalog.pg_class c on (c.oid = cc.conrelid)
                    inner join pg_catalog.pg_attribute a on (
                        a.attrelid = cc.conrelid and a.attnum = any(cc.conkey)
                    )
                    where cc.contype = 'p'
                    group by cc.conrelid, cc.conname
                )
                select format(E'create %stable %s%I (\n%s%s\n);\n',
                    case c.relpersistence when 't' then 'temporary ' else '' end,
                    case c.relpersistence when 't' then '' else n.nspname || '.' end,
                    c.relname,
                    string_agg(
                        format(E'  %I %s%s',
                            a.attname,
                            pg_catalog.format_type(a.atttypid, a.atttypmod),
                            case when a.attnotnull then ' not null' else '' end
                        ), E',\n'
                        order by a.attnum
                    ),
                    (select pkey from pkey where pkey.conrelid = c.oid)
                ) as sql
                from pg_catalog.pg_class c
                inner join pg_catalog.pg_namespace n on (n.oid = c.relnamespace)
                inner join pg_catalog.pg_attribute a on (a.attrelid = c.oid and a.attnum > 0)
                inner join pg_catalog.pg_type t on (a.atttypid = t.oid)
                where n.nspname = ?
                    and c.relname = ?
                group by c.oid, c.relname, c.relpersistence, n.nspname;
                ", $schema, $table
            )
        );
    }

    public function indexRead(): array {
        [$schema, $table] = $this->schemaTable();
        return $this->pgro()->all("
            select indexrelname,
                idx_scan,
                idx_tup_read,
                idx_tup_fetch,
                last_idx_scan
            from pg_stat_all_indexes
            where schemaname = ?
                and relname = ?
            ", $schema, $table
        );
    }

    public function tableRead(): array {
        [$schema, $table] = $this->schemaTable();
        return $this->pgro()->rowAssoc("
            select seq_scan,
                last_seq_scan,
                seq_tup_read,
                idx_scan,
                last_idx_scan,
                idx_tup_fetch,
                n_tup_ins,
                n_tup_upd,
                n_tup_del,
                n_tup_hot_upd,
                n_tup_newpage_upd,
                n_live_tup,
                n_dead_tup,
                n_ins_since_vacuum,
                last_vacuum,
                last_autovacuum,
                vacuum_count,
                autovacuum_count,
                n_mod_since_analyze,
                analyze_count,
                autoanalyze_count
            from pg_stat_user_tables
            where schemaname = ?
                and relname = ?
            ", $schema, $table
        );
    }

    public function stats(): array {
        [$schema, $table] = $this->schemaTable();
        return $this->pg()->rowAssoc("
            select pg_relation_size(t.table_schema || '.' || t.table_name) as table_size,
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
            where t.table_schema = ?
                and t.table_name = ?
            ", $schema, $table
        );
    }
}
