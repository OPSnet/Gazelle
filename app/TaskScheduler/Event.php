<?php

namespace Gazelle\TaskScheduler;

class Event {
    public function __construct(
        public readonly string            $severity,
        public readonly string            $event,
        public readonly int               $reference,
        protected \DateTimeImmutable|null $timestamp = null,
    ) {
        if (is_null($this->timestamp)) {
            $this->timestamp = new \DateTimeImmutable();
        }
    }

    public function timestamp(): \DateTimeImmutable {
        return $this->timestamp;
    }
}
