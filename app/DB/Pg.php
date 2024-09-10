<?php

namespace Gazelle\DB;

class Pg {
    protected \PDO $pdo;

    public function __construct(#[\SensitiveParameter] string $dsn) {
        $this->pdo = new \PDO($dsn);
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }

    public function prepare(string $query): \PDOStatement {
        return $this->pdo->prepare($query);
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function prepared_query(string $query, ...$args): int {
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            return $st->rowCount();
        } else {
            return 0;
        }
    }
    // phpcs:enable

    public function insert(string $query, ...$args): int {
        $st = $this->prepare($query);
        return $st->execute([...$args]) ? (int)$this->pdo->lastInsertId() : 0;
    }

    /**
     * DO NOT USE FOR UNTRUSTED DATA. There can likely be weird cases with specifically crafted data, especially BYTEA.
     */
    public function insertCopy(string $table, array $colList, array $rows): bool {
        if (!$rows) {
            return false;
        }
        static $delimiter = "\t", $nullAs = '\\N';
        $processedRows = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if (is_null($val)) {
                    $vals[] = $nullAs;
                } elseif (is_string($val)) {
                    $vals[] = str_replace(["\\", "\n", "\r", $delimiter], ["\\\\", "\\\n", "\\\r", "\\" . $delimiter], $val);
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
        return $this->pdo->pgsqlCopyFromArray($table, $processedRows, $delimiter, addslashes($nullAs), implode(',', $colList));
    }

    /**
     * Perform an insert or update and return the RETURNING clause
     * Returns `false` if the execute() failed
     */
    public function writeReturning(string $query, ...$args): bool|int|string|float {
        $st = $this->prepare($query);
        return $st->execute([...$args]) ? $st->fetch(\PDO::FETCH_NUM)[0] : false;
    }

    /**
     * Perform an insert or update and return the RETURNING clause,
     * when there is more than one item returned
     */
    public function writeReturningRow(string $query, ...$args): array {
        $st = $this->prepare($query);
        return $st->execute([...$args]) ? $st->fetch(\PDO::FETCH_NUM) : [];
    }

    protected function fetchRow(string $query, int $mode, ...$args): array {
        $st = $this->pdo->prepare($query);
        if ($st !== false && $st->execute([...$args])) {
            $result = $st->fetch($mode);
            if ($result) {
                return $result;
            }
        }
        return [];
    }

    public function scalar(string $query, ...$args): mixed {
        $st = $this->pdo->prepare($query);
        if ($st !== false && $st->execute([...$args])) {
            $result = $st->fetch(\PDO::FETCH_NUM);
            if ($result) {
                return $st->getColumnMeta(0)['native_type'] == 'bytea' /** @phpstan-ignore-line */
                    ? stream_get_contents($result[0])
                    : $result[0];
            }
        }
        return null;
    }

    public function row(string $query, ...$args): array {
        return $this->fetchRow($query, \PDO::FETCH_NUM, ...$args);
    }

    public function rowAssoc(string $query, ...$args): array {
        return $this->fetchRow($query, \PDO::FETCH_ASSOC, ...$args);
    }

    public function all(string $query, ...$args): array {
        $st = $this->pdo->prepare($query);
        if (!$st->execute([...$args])) {
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
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        }
        $result = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach ($needStream as $column) {
                $row[$column] = stream_get_contents($row[$column]);
            }
            $result[] = $row;
        }
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
        $st = $this->pdo->prepare($query);
        if (!$st->execute([...$args])) {
            return [];
        }
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function checkpointInfo(): array {
        $info = $this->rowAssoc("
            select checkpoints_timed,
                checkpoints_req,
                case when checkpoints_timed + checkpoints_req = 0
                    then 0
                    else round(
                        (checkpoints_timed::float / (checkpoints_timed + checkpoints_req) * 100)::numeric,
                        4
                    )
                end as percent
            from pg_stat_bgwriter
        ");
        $info['percent'] = (float)$info['percent'];
        return $info;
    }

    public function version(): string {
        return $this->scalar('select version()');
    }
}
