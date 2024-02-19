<?php

namespace Gazelle\Json\Top10;

class Tag extends \Gazelle\Json {
    public function __construct(
        protected string               $details,
        protected int                  $limit,
        protected \Gazelle\Manager\Tag $manager,
    ) {}

    public function payload(): array {
        $payload = [];

        if (in_array($this->details, ['all', 'ut'])) {
            $payload[] = [
                'caption' => 'Most Used Torrent Tags',
                'tag'     => 'ut',
                'limit'   => $this->limit,
                'results' => $this->manager->topTGroupList($this->limit),
            ];
        }
        if (in_array($this->details, ['all', 'ur'])) {
            $payload[] = [
                'caption' => 'Most Used Request Tags',
                'tag'     => 'ur',
                'limit'   => $this->limit,
                'results' => $this->manager->topRequestList($this->limit),
            ];
        }
        if (in_array($this->details, ['all', 'v'])) {
            $payload[] = [
                'caption' => 'Most Highly Voted Tags',
                'tag'     => 'v',
                'limit'   => $this->limit,
                'results' => $this->manager->topVotedList($this->limit),
            ];
        }

        return $payload;
    }
}
