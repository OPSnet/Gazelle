<?php

namespace Gazelle\DB;

use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\Result;
use sad_spirit\pg_wrapper\converters\containers\ArrayConverter;
use sad_spirit\pg_wrapper\converters\StringConverter;
use sad_spirit\pg_wrapper\exceptions\server\ProgrammingException;

/* There are (at least) two ways to interact with Postgresql databases
 * in PHP: PDO and pg_wrapper. (There are others but they are less
 * interesting).
 *
 * PDO is the standard PHP interface for interacting with a database,
 * but it does not know how to handle advance Pg datatypes.
 * pg_wrapper, on the other hand, knows how to unpack arrays, ranges and
 * the like, which is cannot be done with PDO without additional client
 * make-work code.
 *
 * NOTE!
 * =====
 *
 * pg_wrapper uses $1, $2, $3 numbered parameter placeholders, and the
 * underlying call passes arguments as an passed in an array (as opposed
 * to a variadic function call). This is so confusing compared to the
 * mysql interface that the variadic interface is used here to, even
 * though this differs from the default pg_wrapper style.
 *
 *   create table t (
 *       t_id int not null primary key generated always as identity,
 *       num int[],
 *       word text[]
 *   );
 *   insert into t (num, word) values ('{2, 4, 6}', '{"even"}'),
 *       ('{2, 3, 5, 7, 11}', '{"prime", "primal"}'),
 *       ('{1, 2, 3, 5, 8, 13}', '{"fib"})
 *
 *   $r = $pg->execute("select word from t2 where $1 = any(num);", 3);
 *   $r->fetchall();
 *     → [ ["word" => ["prime", "primal"]], ["word" => ["fib"]] ]
 *
 * Versus:
 *
 *   $pg->all("select word from t2 where ? = any(num);", 3);
 *     → [ ["word" => {prime,primal}], ["word" => "{fib}"] ]
 *
 * ...which leaves the responsibility of parsing and unpacking the
 * returned value "{prime,primal}" correctly.
 *
 * It should be possible to replace all the existing call that rely on PDO
 * to use pg_wrapper instead.
 */

class Pg {
    protected Connection $cnxrw;
    protected \PDO       $pdo;
    protected Pg\Stats   $stats;

    public function __construct(#[\SensitiveParameter] string $dsn) {
        $this->cnxrw = new Connection(
            str_replace(';', ' ', explode(':', $dsn, 2)[1])
        );
        // The DSN of the connection
        $this->pdo   = new \PDO($dsn);
        $this->stats = new Pg\Stats();
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }

    public function stats(): Pg\Stats {
        return $this->stats;
    }

    // ************************* pg_wrapper methods ******************************

    public function cnxrw(): Connection {
        return $this->cnxrw;
    }

    public function arrayConverter(): ArrayConverter {
        return new ArrayConverter(new StringConverter());
    }

    public function execute(string $query): Result {
        $begin = microtime(true);
        try {
            $result = $this->cnxrw->execute($query);
        } catch (ProgrammingException $e) {
            throw new ProgrammingException("{$e->getMessage()} $query");
        }
        $this->stats->register($query, $result->getAffectedRows(), $begin, []);
        return $result;
    }

    /**
     * @param $args array<int, mixed>
     */
    public function executeParams(string $query, mixed ...$args): Result {
        $begin = microtime(true);
        try {
            $result = $this->cnxrw->executeParams($query, [...$args]); /** @phpstan-ignore-line mixed mess */
        } catch (ProgrammingException $e) {
            throw new ProgrammingException(
                "{$e->getMessage()} $query argc=" . count($args) . ' '
                    . json_encode($args, JSON_UNESCAPED_SLASHES)
            );
        }
        $this->stats->register($query, $result->getAffectedRows(), $begin, $args);
        return $result;
    }

    // **************************** PDO methods **********************************

    public function prepare(string $query): \PDOStatement {
        return $this->pdo->prepare($query); /** @phpstan-ignore-line let it blow up downstream */
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function prepared_query(string $query, ...$args): int {
        $begin = microtime(true);
        $st = $this->prepare($query);
        try {
            if ($st->execute([...$args])) {
                $rowCount = $st->rowCount();
                $this->stats->register($query, $rowCount, $begin, [...$args]);
                return $rowCount;
            }
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage()
                . " $query " . json_encode($args, JSON_UNESCAPED_SLASHES));
        }
        $this->stats->error($query);
        return 0;
    }

    // phpcs:enable

    public function insert(string $query, ...$args): int {
        $begin = microtime(true);
        $st = $this->prepare($query);
        try {
            if ($st->execute([...$args])) {
                $id = (int)$this->pdo->lastInsertId();
                $this->stats->register($query, $id, $begin, [...$args]);
                return $id;
            }
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage()
                . " $query " . json_encode($args, JSON_UNESCAPED_SLASHES));
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
