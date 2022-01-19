<?php

namespace Gazelle\Collage;

abstract class AbstractCollage extends \Gazelle\Base {

    protected \Gazelle\Collage $holder;

    protected string $entryTable;
    protected string $entryColumn;
    protected int    $entryTotal = 0;

    protected array $artists      = [];
    protected array $contributors = [];

    abstract public function entryTable(): string;
    abstract public function entryColumn(): string;
    abstract public function load(): int;

    public function __construct(\Gazelle\Collage $holder) {
        $this->holder = $holder;
    }

    public function entryTotal(): int {
        return $this->entryTotal;
    }

    public function artistList(): array {
        return $this->artists;
    }

    public function contributorList(): array {
        return $this->contributors;
    }
}
