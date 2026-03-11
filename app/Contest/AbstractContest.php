<?php

namespace Gazelle\Contest;

use Gazelle\Enum\SourceDB;

abstract class AbstractContest extends \Gazelle\Base {
    public function __construct(
        protected readonly int $id,
        protected string $begin,
        protected string $end,
    ) {}

    public function rankerSource(): SourceDB {
        return SourceDB::mysql;
    }

    abstract public function ranker(): array;

    abstract public function participationStats(): array;

    abstract public function userPayout(): array;
}
