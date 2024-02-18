<?php

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
        return trim((string)shell_exec(BIN_GIT . ' rev-parse origin/' . $this->gitBranch()));
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
            [$uptime, $idletime] = array_map('floatval', explode(' ', trim((string)fgets($in))));
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
                fn($v) => count($v) > 0
            )
        ];
    }

    public function composerVersion(): string {
        return trim((string)shell_exec(BIN_COMPOSER . ' --version 2>/dev/null'));
    }

    public function composerPackages(): array {
        $packages = [];
        $root = realpath(__DIR__ . '/../');

        $composer = json_decode((string)file_get_contents((string)realpath("$root/composer.json")), true);
        foreach ($composer['require'] as $name => $version) {
            if ($name != 'php') {
                $packages[$name] = [
                    'name'    => $name,
                    'require' => $version,
                    'installed' => null,
                ];
            }
        }

        $lock = json_decode((string)file_get_contents((string)realpath("$root/composer.lock")), true);
        foreach ($lock['packages'] as $p) {
            if (isset($packages[$p['name']])) {
                if ($p['name'] == $packages[$p['name']]['name']) {
                    $packages[$p['name']]['installed'] = $p['version'];
                }
            }
        }

        $info = json_decode((string)shell_exec(BIN_COMPOSER . " info -d $root --format=json 2>/dev/null"), true);
        foreach ($info['installed'] as $p) {
            if (!isset($packages[$p['name']])) {
                $packages[$p['name']] = [
                    'name'      => $p['name'],
                    'installed' => $p['version'],
                    'require'   => '-',
                ];
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
            ", SQLDB, $tableName
        );
    }

    public function tablesWithoutPK(): array {
        self::$db->prepared_query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_name NOT IN (
                SELECT DISTINCT TABLE_NAME
                FROM information_schema.statistics
                WHERE INDEX_NAME = 'PRIMARY' AND  TABLE_SCHEMA = ?
            ) AND TABLE_SCHEMA = ?
            ORDER BY TABLE_NAME
            ", SQLDB, SQLDB
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
            ", SQLDB, SQLDB
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function tableRowsRead(string $tableName): array {
        self::$db->prepared_query("
            SELECT ROWS_READ, ROWS_CHANGED, ROWS_CHANGED_X_INDEXES
            FROM information_schema.table_statistics
            WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
            ", SQLDB, $tableName
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function indexRowsRead(string $tableName): array {
        self::$db->prepared_query("
            SELECT DISTINCT s.INDEX_NAME,
                coalesce(si.ROWS_READ, 0) as ROWS_READ
            FROM information_schema.statistics s
            LEFT JOIN information_schema.index_statistics si USING (TABLE_SCHEMA, TABLE_NAME, INDEX_NAME)
            WHERE s.TABLE_SCHEMA = ?
                AND s.TABLE_NAME = ?
            ORDER BY s.TABLE_NAME,
                s.INDEX_NAME = 'PRIMARY' DESC,
                coalesce(si.ROWS_READ, 0) DESC,
                s.INDEX_NAME
            ", SQLDB, $tableName
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function tableStats(string $tableName): array {
        return self::$db->rowAssoc("
            SELECT t.table_rows,
                t.avg_row_length,
                t.data_length,
                t.index_length,
                t.data_free
            FROM information_schema.tables t
            WHERE t.table_schema = ?
                AND t.table_name = ?
            ", SQLDB, $tableName
        );
    }
}
