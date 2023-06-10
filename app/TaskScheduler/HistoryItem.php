<?php

namespace Gazelle\TaskScheduler;

class HistoryItem {
    public function __construct(
        public readonly string $launchTime,
        public readonly string $status,
        public readonly int $numErrors,
        public readonly int $numItems,
        public readonly int $duration,
        public readonly array $events = [],
    ) {}
}
