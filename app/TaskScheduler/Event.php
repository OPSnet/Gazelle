<?php

namespace Gazelle\TaskScheduler;

class Event {
    public string $timestamp;

    public function __construct(
        public readonly string $severity,
        public readonly string $event,
        public readonly int    $reference,
        string|null            $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? date('Y-m-d H:i:s');
    }
}
