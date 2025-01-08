<?php

namespace Gazelle\DB;

use Gazelle\DB\Pg\Stats;

class Pg {
    protected \PDO  $pdo;
    protected Stats $stats;

    public function __construct(#[\SensitiveParameter] string $dsn) {
        $this->pdo   = new \PDO($dsn);
        $this->stats = new Stats();
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }

    public function stats(): Stats {
        return $this->stats;
    }

    public function prepare(string $query): \PDOStatement {
        return $this->pdo->prepare($query); /** @phpstan-ignore-line let it blow up downstream */
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function prepared_query(string $query, ...$args): int {
        $begin = microtime(true);
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            $rowCount = $st->rowCount();
            $this->stats->register($query, $rowCount, $begin, [...$args]);
            return $rowCount;
        }
        $this->stats->error($query);
        return 0;
    }

    // phpcs:enable

    public function insert(string $query, ...$args): int {
        $begin = microtime(true);
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            $id = (int)$this->pdo->lastInsertId();
            $this->stats->register($query, $id, $begin, [...$args]);
            return $id;
        }
        $this->stats->error($query);
        return 0;
    }

    /**
     * DO NOT USE FOR UNTRUSTED DATA. There can likely be weird cases with specifically crafted data, especially BYTEA.
     */
    public function insertCopy(string $table, array $colList, array $rows): bool {
        if (!$rows) {
            return false;
        }
        static $delimiter = "\t", $nullAs = '\\N';
        $begin = microtime(true);
        $processedRows = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if (is_null($val)) {
                    $vals[] = $nullAs;
                } elseif (is_string($val)) {
                    $vals[] = str_replace(
                        ["\\",   "\n",   "\r",   $delimiter],
                        ["\\\\", "\\\n", "\\\r", "\\" . $delimiter],
                        $val
                    );
                } elseif (is_bool($val)) {
                    $vals[] = $val ? 't' : 'f';
                } elseif (is_int($val) || is_float($val)) {
                    $vals[] = $val;
                } else {
                    throw new \Exception("invalid column data type");
                }
            }
            $processedRows[] = implode($delimiter, $vals);
        }
        $success = $this->pdo->pgsqlCopyFromArray(
            $table,
            $processedRows,
            $delimiter,
            addslashes($nullAs),
            implode(',', $colList)
        );
        $this->stats->register("copy $table", count($processedRows), $begin, $colList);
        return $success;
    }

    /**
     * Perform an insert or update and return the RETURNING clause
     * Returns `false` if the execute() failed
     */
    public function writeReturning(string $query, ...$args): int|string|float|false {
        $begin = microtime(true);
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            $result = $st->fetch(\PDO::FETCH_NUM)[0];
            $this->stats->register($query, (int)$result, $begin, [...$args]);
            return $result;
        }
        $this->stats->error($query);
        return false;
    }

    /**
     * Perform an insert or update and return the RETURNING clause,
     * when there is more than one item returned
     */
    public function writeReturningRow(string $query, ...$args): array {
        $begin = microtime(true);
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            $row = $st->fetch(\PDO::FETCH_NUM);
            $this->stats->register($query, count($row), $begin, [...$args]);
            return $row;
        }
        $this->stats->error($query);
        return [];
    }

    protected function fetchRow(string $query, int $mode, ...$args): array {
        $begin = microtime(true);
        $st = $this->pdo->prepare($query);
        if ($st !== false && $st->execute([...$args])) {
            $row = $st->fetch($mode);
            if ($row) {
                $this->stats->register($query, count($row), $begin, [...$args]);
                return $row;
            }
        }
        $this->stats->error($query);
        return [];
    }

    public function scalar(string $query, ...$args): mixed {
        $begin = microtime(true);
        $st = $this->pdo->prepare($query);
        if ($st !== false && $st->execute([...$args])) {
            $result = $st->fetch(\PDO::FETCH_NUM);
            if ($result) {
                $this->stats->register($query, 0, $begin, [...$args]);
                return $st->getColumnMeta(0)['native_type'] == 'bytea' /** @phpstan-ignore-line */
                    ? stream_get_contents($result[0])
                    : $result[0];
            }
        }
        $this->stats->error($query);
        return null;
    }

    public function row(string $query, ...$args): array {
        return $this->fetchRow($query, \PDO::FETCH_NUM, ...$args);
    }

    public function rowAssoc(string $query, ...$args): array {
        return $this->fetchRow($query, \PDO::FETCH_ASSOC, ...$args);
    }

    public function all(string $query, ...$args): array {
        $begin = microtime(true);
        $st = $this->pdo->prepare($query);
        if ($st === false || !$st->execute([...$args])) {
            $this->stats->error($query);
            return [];
        }
        // If any columns are of datatype bytea then we get the contents
        // from the stream immediately. As long as multi-GiB blobs are
        // not stored in the db, this will be perfecly safe.
        // If you want to store multi-GiB blobs, the design needs a rethink.
        $needStream = [];
        for ($end = $st->columnCount(), $i = 0; $i < $end; ++$i) {
            $meta = $st->getColumnMeta($i);
            if ($meta['native_type'] === 'bytea') { /** @phpstan-ignore-line */
                $needStream[] = $meta['name'];
            }
        }
        if (!$needStream) {
            $result = $st->fetchAll(\PDO::FETCH_ASSOC);
            $this->stats->register($query, count($result), $begin, [...$args]);
            return $result;
        }
        $result = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach ($needStream as $column) {
                $row[$column] = stream_get_contents($row[$column]);
            }
            $result[] = $row;
        }
        $this->stats->register($query, count($result), $begin, [...$args]);
        return $result;
    }

    public function allByKey(string $key, string $query, ...$args): array {
        $list = [];
        foreach ($this->all($query, ...$args) as $row) {
            $list[$row[$key]] = $row;
        }
        return $list;
    }

    public function column(string $query, ...$args): array {
        $begin = microtime(true);
        $st = $this->pdo->prepare($query);
        if ($st === false || !$st->execute([...$args])) {
            $this->stats->error($query);
            return [];
        }
        $result = $st->fetchAll(\PDO::FETCH_COLUMN);
        $this->stats->register($query, count($result), $begin, [...$args]);
        return $result;
    }

    public function checkpointInfo(): array {
        $version = (int)$this->scalar("
            select current_setting('server_version_num')
        ");
        $query = $version < 170000
            ? '
            select checkpoints_timed as num_timed,
                checkpoints_req as num_requested,
                case when checkpoints_timed + checkpoints_req = 0
                    then 0
                    else round(
                        (checkpoints_timed::float / (checkpoints_timed + checkpoints_req) * 100)::numeric,
                        4
                    )
                end as percent
            from pg_stat_bgwriter
            '
            : '
            select num_timed,
                num_requested,
                case when num_timed + num_requested = 0
                    then 0
                    else round(
                        (num_timed::float / (num_timed + num_requested) * 100)::numeric,
                        4
                    )
                end as percent
            from pg_stat_checkpointer
            ';
        $info = $this->rowAssoc($query);
        $info['percent'] = (float)$info['percent'];
        return $info;
    }

    public function version(): string {
        return $this->scalar('select version()');
    }
}
