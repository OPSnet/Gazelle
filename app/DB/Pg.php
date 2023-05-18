<?php

namespace Gazelle\DB;

class Pg {
    protected \PDO $pdo;

    public function __construct(string $dsn) {
        $this->pdo = new \PDO($dsn);
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }

    public function prepare(string $query): \PDOStatement {
        return $this->pdo->prepare($query);
    }

    public function prepared_query(string $query, ...$args): int {
        $st = $this->prepare($query);
        if ($st->execute([...$args])) {
            return $st->rowCount();
        } else {
            return 0;
        }
    }

    public function insert(string $query, ...$args): int {
        $st = $this->prepare($query);
        return $st->execute([...$args]) ? (int)$this->pdo->lastInsertId() : 0;
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
        return $st->fetchAll(\PDO::FETCH_ASSOC);
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
}
