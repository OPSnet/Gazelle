<?php

namespace Gazelle;

use Gazelle\Util\Time;

class SiteInfo extends Base {

    public function gitBranch() {
        return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    }

    public function gitHash() {
        return trim(shell_exec('git rev-parse HEAD'));
    }

    public function gitHashRemote() {
        return trim(shell_exec("git rev-parse origin/" . $this->gitBranch()));
    }

    public function phpinfo() {
        ob_start();
        phpinfo();
        $p = ob_get_contents();
        ob_end_clean();
        return substr($p, strpos($p, '<body>') + 6, strpos($p, '</body>'));
    }

    public function uptime() {
        $in = fopen('/proc/uptime', 'r');
        list($uptime, $idletime) = explode(' ', trim(fgets($in)));
        fclose($in);
        $in = fopen('/proc/cpuinfo', 'r');
        $ncpu = 0;
        while (($line = fgets($in)) !== false) {
           if (preg_match('/^processor\s+:\s+\d+/', $line)) {
            ++$ncpu;
            }
        }
        fclose($in);
        $now = time();
        $idletime = str_replace(' ago', '',
            Time::timeDiff($now - (int)($idletime / $ncpu))
        );
        return [
            'uptime'   => Time::timeDiff($now - (int)$uptime),
            'idletime' => $idletime,
        ];
    }

    public function phinx() {
        $phinxBinary = realpath(__DIR__ . '/../vendor/bin/phinx');
        $phinxScript = realpath(__DIR__ . '/../phinx.php');
        return [
            'version' => explode(' ', shell_exec("$phinxBinary --version"))[5],
            'migration' => array_filter(
                json_decode(
                    shell_exec("$phinxBinary status -c $phinxScript --format=json|tail -n 1"),
                    true
                )['migrations'],
                function($v) { return count($v) > 0; }
            )
        ];
    }

    public function composerVersion() {
        return trim(shell_exec('composer --version 2>/dev/null'));
    }

    public function composerPackages() {
        $packages = [];
        $root = realpath(__DIR__ . '/../');

        $composer = json_decode(file_get_contents(realpath("$root/composer.json")), true);
        foreach ($composer['require'] as $name => $version) {
            if ($name != 'php') {
                $packages[$name] = [
                    'name'    => $name,
                    'require' => $version,
                    'installed' => null,
                ];
            }
        }

        $lock = json_decode(file_get_contents(realpath("$root/composer.lock")), true);
        foreach ($lock['packages'] as $p) {
            if (isset($packages[$p['name']])) {
                if ($p['name'] == $packages[$p['name']]['name']) {
                    $packages[$p['name']]['installed'] = $p['version'];
                }
            }
        }

        $info = json_decode(shell_exec("composer info -d $root --format=json 2>/dev/null"), true);
        foreach ($info['installed'] as $name => $p) {
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
