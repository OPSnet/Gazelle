<?php

namespace Gazelle\DB;

class Pg {
    protected \PDO $pdo;

    public function __construct(string $dsn) {
        $this->pdo = new \PDO($dsn);
    }

    protected function fetchRow(string $query, int $mode, ...$args): array {
        $st = $this->pdo->prepare($query);
        if (!$st->execute([...$args])) {
            return [];
        }
        return $st->fetch($mode);
    }

    public function scalar(string $query, ...$args) {
        $row = $this->fetchRow($query, \PDO::FETCH_NUM, ...$args);
        return $row ? $row[0] : null;
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
}
