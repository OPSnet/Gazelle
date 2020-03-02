<?php

namespace Gazelle\Schedule;

class TaskHistory
{
    public $name;
    public $count;
    public $items;

    public function __construct(string $name, int $count, array $items = null) {
        $this->name = $name;
        $this->count = $count;
        $this->items = $items === null ? [] : $items;
    }
}
