<?php

declare(strict_types=1);

namespace Gazelle\DB\Pg;

class Stats {
    protected static array $query = [];
    protected static array $error = [];

    /**
     * The statistics are never flushed in production, but the ability comes
     * in handy for unit tests and local development (when you need to start
     * with a clean slate).
     */
    public function flush(): static {
        self::$query = [];
        self::$error = [];
        return $this;
    }

    public function errorList(): array {
        return self::$error;
    }

    public function queryList(): array {
        return self::$query;
    }

    public function totalDuration(): float {
        $sum = 0.0;
        return (float)array_reduce(
            $this->queryList(),
            fn ($sum, $query) => $sum + $query['duration']
        );
    }

    public function register(string $query, int $metric, float $begin, array $args): int {
        $now = microtime(true);
        self::$query[] = [
            'args'     => $args,
            'duration' => $now - $begin,
            'epoch'    => $now,
            'metric'   => $metric,
            'query'    => $query,
        ];
        return count(self::$query);
    }

    public function error(string $query): int {
        self::$error[] = [
            'epoch' => microtime(true),
            'query' => $query,
        ];
        return count(self::$error);
    }
}
