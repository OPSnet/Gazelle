<?php

namespace Gazelle\Contest;

abstract class AbstractContest extends \Gazelle\Base {
    public function __construct(
        protected readonly int $id,
        protected string $begin,
        protected string $end,
    ) {}

    abstract public function ranker(): array;
    abstract public function participationStats(): array;
    abstract public function userPayout(int $enabledUserBonus, int $contestBonus, int $perEntryBonus): array;
}
