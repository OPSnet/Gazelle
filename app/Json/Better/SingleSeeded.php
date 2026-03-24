<?php

namespace Gazelle\Json\Better;

class SingleSeeded extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User                $user,
        protected \Gazelle\Better\SingleSeeded $better,
    ) {}

    public function payload(): array {
        return array_map(
            fn ($torrent) => [
                'torrentId'   => $torrent->id,
                'groupId'     => $torrent->groupId(),
                'artistInfo'  => static::artistPayload($torrent->group()),
                'groupName'   => $torrent->group()->name(),
                'groupYear'   => $torrent->group()->year(),
                'downloadUrl' => "torrents.php?action=download&id={$torrent->id}&torrent_pass={$this->user->announceKey()}",
                'source'      => $torrent->location(),
            ], $this->better->page(50, 0)
        );
    }
}
