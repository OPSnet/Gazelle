<?php

namespace Gazelle\Manager;

class Counter {
    use \Gazelle\Pg;

    public function create(string $name, string $description, int $value = 0): \Gazelle\Counter {
        $this->pg()->prepared_query("
            insert into counter
                   (name, description, value)
            values (?,    ?,           ?)
            ", $name, $description, $value
        );
        return $this->find($name);
    }

    public function find(string $name): ?\Gazelle\Counter {
        $counter = $this->pg()->scalar("
            select name from counter where name = ?
            ", $name
        );
        return $counter ? new \Gazelle\Counter($counter) : null;
    }
}
