<?php

namespace Gazelle\TaskScheduler;

class HistoryItem {
    public function __construct(
        protected string $launchTime,
        protected string $status,
        protected int $numErrors,
        protected int $numItems,
        protected int $duration,
        protected array $events = [],
    ) {}
}
