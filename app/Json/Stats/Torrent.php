<?php

namespace Gazelle\Json\Stats;

class Torrent extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Stats\Torrent $stat,
    ) {}

    public function payload(): array {
        $flow = [];
        foreach ($this->stat->flow() as $month) {
            $flow[$month['Month']] = [
                'Month' => $month['Month'],
                'uploads'   =>  $month['t_add'],
                'deletions' => -$month['t_del'],
                'remaining' =>  $month['t_net'],
            ];
        }

        return [
            'uploads_by_month'     => $flow,
            'torrents_by_category' => $this->stat->categoryList(),
        ];
    }
}
