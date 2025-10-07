<?php

namespace Gazelle\Json\Stats;

class User extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User $viewer,
        protected \Gazelle\Stats\Users $stat = new \Gazelle\Stats\Users(),
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
