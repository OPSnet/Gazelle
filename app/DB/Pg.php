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
        $row = $this->fetchRow($query, \PDO::FETCH_NUM, ...$args);
        return empty($row) ? null : $row[0];
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

    public function column(string $query, ...$args): array {
        $st = $this->pdo->prepare($query);
        if (!$st->execute([...$args])) {
            return [];
        }
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }
}
