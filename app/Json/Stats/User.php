<?php

namespace Gazelle\Json\Stats;

class User extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Stats\Users $stat,
        protected \Gazelle\User $viewer,
    ) {}

    public function payload(): array {
        return [
            'flow'      => $this->stat->flow(),
            'browsers'  => $this->stat->browserDistributionList($this->viewer->permitted('users_mod')),
            'classes'   => $this->stat->userclassDistributionList($this->viewer->permitted('users_mod')),
            'platforms' => $this->stat->platformDistributionList($this->viewer->permitted('users_mod')),
        ];
    }
}
