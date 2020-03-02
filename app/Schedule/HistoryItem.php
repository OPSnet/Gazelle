<?php

namespace Gazelle\Schedule;

class HistoryItem
{
    public $launchTime;
    public $status;
    public $numErrors;
    public $numItems;
    public $duration;
    public $events;

    public function __construct(string $launchTime, string $status, int $numErrors, int $numItems, int $duration, array $events = null) {
        $this->launchTime = $launchTime;
        $this->status = $status;
        $this->numErrors = $numErrors;
        $this->numItems = $numItems;
        $this->duration = $duration;
        $this->events = $events === null ? [] : $events;
    }
}
