<?php

namespace Gazelle\Manager;

class Counter {
    use \Gazelle\Pg;

    public function create(string $name, string $description, int $value = 0): \Gazelle\Counter {
        $this->pg()->query("
            insert into counter
                   (name, description, value)
            values ($1,   $2,          $3)
            ", $name, $description, $value
        );
        return $this->find($name);
    }

    public function find(string $name): ?\Gazelle\Counter {
        $counter = $this->pg()->scalarex("
            select name from counter where name = $1
            ", $name
        );
        return $counter ? new \Gazelle\Counter($counter) : null;
    }
}
