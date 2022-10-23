<?php

namespace Gazelle;

abstract class BaseManager extends Base {
    /**
     * A class that derives from BaseManager is guaranteed to
     * have a method findById() that can be used to retrieve
     * Gazelle objects. Handy if you want to pass arbitrary
     * managers around.
     */
    abstract public function findById(int $id): ?\Gazelle\Base;
}
