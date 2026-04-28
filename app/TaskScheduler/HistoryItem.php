<?php

namespace Gazelle\TaskScheduler;

class HistoryItem {
    public function __construct(
        public readonly \DateTimeImmutable $created,
        public readonly string             $status,
        public readonly int                $errorTotal,
        public readonly int                $itemTotal,
        public readonly int                $duration,
        public readonly array              $events = [],
    ) {}
}
