<?php

declare(strict_types=1);

namespace Gazelle\Search\UserTorrent;

use Gazelle\User;

abstract class AbstractSearch extends \Gazelle\Base {
    protected array $cond = [];
    protected array $args = [];

    abstract public function setUser(User $user, User $viewer): static;

    abstract public function join(): string;

    abstract public function orderColumn(): string;

    public function __construct(User $user, User $viewer) {
        $this->setUser($user, $viewer);
    }

    public function args(): array {
        return $this->args;
    }

    public function where(): string {
        if ($this->cond === []) {
            // select nothing
            $this->cond[] = 'false';
        }
        return implode(' and ', $this->cond);
    }
}
