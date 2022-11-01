<?php

namespace Gazelle\Schedule;

class TaskHistory {
    public function __construct(
        public readonly string $name,
        public readonly int $count,
        public array $items = [],
    ) {}
}
