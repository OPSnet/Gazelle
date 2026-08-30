<?php

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\Time;

class SiteInfo extends Base {
    public function gitBranch(): string {
        return trim((string)shell_exec(BIN_GIT . ' rev-parse --abbrev-ref HEAD'));
    }

    public function gitHash(): string {
        return trim((string)shell_exec(BIN_GIT . ' rev-parse HEAD'));
    }

    public function gitHashRemote(): string {
        $process = proc_open(
            [BIN_GIT, 'rev-parse', "origin/{$this->gitBranch()}"],
            [
               0 => ["pipe", "r"],  // stdin
               1 => ["pipe", "w"],  // stdout
               2 => ["pipe", "w"],  // stderr
            ],
            $output,
        );
        if ($process === false) {
            return '';
        }
        $remote = (string)stream_get_contents($output[1]);
        proc_close($process);
        return $remote;
    }

    public function phpinfo(): string {
        ob_start();
        phpinfo();
        $p = (string)ob_get_contents();
        ob_end_clean();
        return substr($p, (int)strpos($p, '<body>') + 6, (int)strpos($p, '</body>'));
    }

    public function uptime(): array {
        $in = fopen('/proc/uptime', 'r');
        if ($in === false) {
            [$uptime, $idletime] = [0, 0];
        } else {
            [$uptime, $idletime] = array_map(floatval(...), explode(' ', trim((string)fgets($in))));
            fclose($in);
        }
        $ncpu = 0;
        $in = fopen('/proc/cpuinfo', 'r');
        if ($in !== false) {
            while (($line = fgets($in)) !== false) {
                if (preg_match('/^processor\s+:\s+\d+/', $line)) {
                    ++$ncpu;
                }
            }
            fclose($in);
        }
        $now = time();
        return [
            'uptime'   => Time::diff($now - (int)$uptime),
            'idletime' => Time::diff($now - (int)($idletime / $ncpu), 2, true, false, true),
        ];
    }

    public function phinx(): array {
        return [
            'version' => explode(' ', (string)shell_exec(BIN_PHINX . ' --version'))[5],
            'migration' => array_filter(
                json_decode(
                    (string)shell_exec(BIN_PHINX . ' status -c ' . PHINX_MYSQL . ' --format=json|tail -n 1'),
                    true
                )['migrations'],
                fn ($v) => count($v) > 0
            )
        ];
    }

    public function phinxMigrationsPending(): bool {
        $pendingMigrations = false;
        foreach ([PHINX_MYSQL, PHINX_MYSQL2, PHINX_PG] as $configuration) {
            $output = shell_exec(
                escapeshellarg(BIN_PHINX) . " status -c " . escapeshellarg($configuration)
                    . " --format=json | tail -n 1"
            );
            if ($output !== false) {
                $status = json_decode($output, true);
                if (is_array($status) && $status['pending_count'] !== 0) {
                    $pendingMigrations = true;
                    break;
                }
            }
        }
        return $pendingMigrations;
    }

    public function composerVersion(): string {
        return trim((string)shell_exec(BIN_COMPOSER . ' --version 2>/dev/null'));
    }

    public function composerPackages(): array {
        $packages = [];
        $root = realpath(__DIR__ . '/../');

        $info = json_decode((string)shell_exec(BIN_COMPOSER . " info -d $root --format=json --no-dev 2>/dev/null"), true);
        foreach ($info['installed'] as $p) {
            $packages[$p['name']] = [
                'name'      => $p['name'],
                'require'   => '-',
                'locked'    => '-',
                'installed' => str_replace(' ', '#', $p['version']),
            ];
        }

        $composer = json_decode((string)file_get_contents((string)realpath("$root/composer.json")), true);
        foreach ($composer['require'] as $name => $version) {
            if (isset($packages[$name])) {
                if (str_starts_with($version, 'dev-')) {
                    $parts = explode("#", $version);
                    $version = $parts[0] . '#' . substr($parts[1], 0, 7);
                }
                $packages[$name]['require'] = $version;
            }
        }

        $lock = json_decode((string)file_get_contents((string)realpath("$root/composer.lock")), true);
        foreach ($lock['packages'] as $p) {
            if (isset($packages[$p['name']])) {
                $version = $p['version'];
                if (str_starts_with($version, 'dev-')) {
                    $version .= '#' . substr($p['source']['reference'], 0, 7);
                }
                $packages[$p['name']]['locked'] = $version;
            }
        }

        ksort($packages);
        return $packages;
    }

    public function tableExists(string $tableName): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM information_schema.tables t
            WHERE t.table_schema = ?
                AND t.table_name = ?
            ", MYSQL_DB, $tableName
        );
    }

    public function tablesWithoutPK(): array {
        self::$db->prepared_query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_type = 'BASE TABLE'
                AND table_name NOT IN (
                    SELECT DISTINCT TABLE_NAME
                    FROM information_schema.statistics
                    WHERE INDEX_NAME = 'PRIMARY' AND  TABLE_SCHEMA = ?
                )
                AND TABLE_SCHEMA = ?
            ORDER BY TABLE_NAME
            ", MYSQL_DB, MYSQL_DB
        );
        return self::$db->collect(0);
    }

    /**
     * List of tables that have multiple foreign keys on the same referenced table.
     * This can occur due to the use of online schema changers.
     * Dropping a redundant key is as simple as
     * ALTER TABLE table_name DROP CONSTRAINT constraint_name
     */
    public function tablesWithDuplicateForeignKeys(): array {
        self::$db->prepared_query("
            SELECT kcu.table_name,
                kcu.column_name,
                kcu.referenced_table_name,
                kcu.referenced_column_name,
                kcu.constraint_name
            FROM information_schema.key_column_usage kcu
            INNER JOIN (
                SELECT table_name,
                    column_name,
                    referenced_table_name,
                    referenced_column_name 
                FROM information_schema.key_column_usage 
                WHERE referenced_table_schema = ?
                GROUP BY table_name, column_name, referenced_table_name, referenced_column_name 
                HAVING count(*) > 1
            ) DUP USING (table_name, column_name, referenced_table_name, referenced_column_name)
            WHERE kcu.referenced_table_schema = ?
            ORDER BY 1, 2, 3, 4, 5;
            ", MYSQL_DB, MYSQL_DB
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function tableRowsRead(string $tableName): array {
        self::$db->prepared_query("
            SELECT ROWS_READ, ROWS_CHANGED, ROWS_CHANGED_X_INDEXES
            FROM information_schema.table_statistics
            WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
            ", MYSQL_DB, $tableName
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function indexRowsRead(string $tableName): array {
        self::$db->prepared_query("
            SELECT s.INDEX_NAME           AS index_name,
                coalesce(si.ROWS_READ, 0) AS rows_read,
                group_concat(
                    concat(s.column_name, ' {', s.cardinality, '}')
                    ORDER BY s.seq_in_index
                    SEPARATOR ', '
                ) AS column_list
            FROM information_schema.statistics s
            LEFT JOIN information_schema.index_statistics si USING (TABLE_SCHEMA, TABLE_NAME, INDEX_NAME)
            WHERE s.TABLE_SCHEMA = ?
                AND s.TABLE_NAME = ?
            GROUP BY index_name,
                rows_read
            ORDER BY s.TABLE_NAME,
                s.INDEX_NAME = 'PRIMARY' DESC,
                coalesce(si.ROWS_READ, 0) DESC,
                s.INDEX_NAME
            ", MYSQL_DB, $tableName
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function tableStats(string $tableName): array {
        return self::$db->rowAssoc("
            SELECT t.TABLE_ROWS,
                t.AVG_ROW_LENGTH,
                t.DATA_LENGTH,
                t.INDEX_LENGTH,
                t.DATA_FREE,
                ts.ROWS_READ,
                ts.ROWS_CHANGED,
                ts.ROWS_CHANGED_X_INDEXES
            FROM information_schema.tables t
            INNER JOIN information_schema.table_statistics ts USING (TABLE_SCHEMA, TABLE_NAME)
            WHERE t.TABLE_SCHEMA = ?
                AND t.TABLE_NAME = ?
            ", MYSQL_DB, $tableName
        );
    }

    public function refreshHistorySiteMetric(): int {
        $begin    = new \DateTime('yesterday');
        $end      = new \DateTime('today');
        $inserted = 0;

        if ($this->refreshHistorySiteMetricNeeded('request-filled')) {
            // if filled is missing, unfilled is too
            $inserted += $this->pg()->query(<<<END_SQL
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    coalesce(
                        case sm.name
                            when 'request-filled'   then sum(
                                case when r.id_filler is null
                                    then 0
                                    else 1
                                end
                            )
                            when 'request-unfilled' then sum(
                                case when r.id_filler is null
                                    /* special case no rows in request table */
                                    then case when r.id_request is null then 0 else 1 end
                                    else 0
                                end
                            )
                        end,
                        0
                    )
                from site_metric sm
                left join request r on (true)
                where sm.name in ('request-unfilled', 'request-filled')
                    and (r.id_request is null or r.created < $2)
                group by sm.id_site_metric,
                    sm.name
                END_SQL, $begin, $end
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('snatch-lossless')) {
            $inserted += $this->pg()->query(<<<END_SQL
                with snatch as (
                    select
                        coalesce(sum(case when t."Format" = 'FLAC' then 1 else 0 end), 0) as lossless,
                        coalesce(sum(case when t."Format" = 'FLAC' then 0 else 1 end), 0) as lossy
                    from relay.xbt_snatched   xs
                    inner join relay.torrents t  on (t."ID" = xs.fid)
                    where xs.tstamp >= extract(epoch from $1::date)
                        and xs.tstamp < extract(epoch from $2::date)
                )
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    case
                        when sm.name = 'snatch-lossless' then snatch.lossless
                        when sm.name = 'snatch-lossy'    then snatch.lossy
                    end
                from snatch
                cross join site_metric sm
                where sm.name in ('snatch-lossless', 'snatch-lossy');
                END_SQL, $begin, $end
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('torrent-seeding')) {
            // and torrent-leeching
            $inserted += $this->pg()->query(<<<END_SQL
                with torrent as (
                    select
                        coalesce(sum(case when xfu.remaining = 0 then 1 else 0 end), 0) as seeding,
                        coalesce(sum(case when xfu.remaining = 0 then 0 else 1 end), 0) as leeching
                    from relay.xbt_files_users xfu
                )
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    case
                        when sm.name = 'torrent-seeding'  then torrent.seeding
                        when sm.name = 'torrent-leeching' then torrent.leeching
                    end
                from torrent
                cross join site_metric sm
                where sm.name in ('torrent-seeding', 'torrent-leeching');
                END_SQL, $begin
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('torrent-created')) {
            $inserted += $this->pg()->query(<<<END_SQL
                with torrent(metric) as (
                    select count(*)
                    from relay.torrents t
                    where t.created >= $1
                        and t.created < $2
                )
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    torrent.metric
                from torrent
                cross join site_metric sm
                where sm.name = 'torrent-created'
                END_SQL, $begin, $end
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('torrent-single-seed-flac')) {
            $inserted += $this->pg()->query(<<<END_SQL
                with ssf(metric) as (
                    select count(*)
                    from relay.torrents t
                    inner join relay.torrents_leech_stats tls on (tls."TorrentID" = t."ID")
                    where t."Format"      = 'FLAC'
                        and tls."Seeders" = 1
                )
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    coalesce(ssf.metric, 0)
                from ssf
                cross join site_metric sm
                where sm.name = 'torrent-single-seed-flac';
                END_SQL, $begin
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('user-enable')) {
            // and user-disable
            $inserted += $this->pg()->query(<<<END_SQL
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    coalesce(
                        case sm.name
                            when 'user-enable'  then sum(case when um."Enabled" = $2 then 1 else 0 end)
                            when 'user-disable' then sum(case when um."Enabled" = $2 then 0 else 1 end)
                        end,
                        0
                    )
                from relay.users_main  um
                cross join site_metric sm
                where sm.name in ('user-enable', 'user-disable')
                group by sm.id_site_metric,
                    sm.name
                END_SQL, $begin, Enum\UserStatus::enabled->value
            );
        }

        if ($this->refreshHistorySiteMetricNeeded('user-ratio')) {
            $inserted += $this->pg()->query(<<<END_SQL
                with ratio(metric) as (
                    select coalesce(count(*), 0)
                    from relay.users_info  ui
                    where ui."RatioWatchEnds" > $1
                )
                insert into history_site_metric (id_site_metric, dt, metric)
                select sm.id_site_metric,
                    $1::date,
                    ratio.metric
                from ratio
                cross join site_metric sm
                where sm.name = 'user-ratio'
                END_SQL, $begin
            );
        }
        return $inserted;
    }

    public function refreshHistorySiteMetricNeeded(string $name): bool {
        // the task to update the metrics is run several times a day to take
        // into account the possibility of the database being stopped for
        // maintenance, or a query dying due to a deadlock.
        return !(bool)$this->pg()->scalarex("
            select 1
            from history_site_metric hsm
            inner join site_metric sm using (id_site_metric)
            where hsm.dt = (now() - interval '1 day')::date
                and sm.name = $1
            ", $name
        );
    }
}
