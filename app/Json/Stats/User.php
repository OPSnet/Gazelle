<?php

namespace Gazelle\Json\Stats;

class User extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Stats\Users $stat
    ) {}

    public function payload(): array {
        return [
            'flow'      => $this->stat->flow(),
            'classes'   => $this->stat->userclassDistributionList(),
            'platforms' => $this->stat->platformDistributionList(),
            'browsers'  => $this->stat->browserDistributionList(),
        ];
    }
}
