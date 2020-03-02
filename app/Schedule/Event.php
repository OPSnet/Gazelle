<?php

namespace Gazelle\Schedule;

class Event {
    public $severity;
    public $timestamp;
    public $event;
    public $reference;

    public function __construct(string $severity, string $event, int $reference = 0, string $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = \Gazelle\Util\Time::sqlTime();
        }

        $this->severity = $severity;
        $this->timestamp = $timestamp;
        $this->event = $event;
        $this->reference = $reference;
    }
}
