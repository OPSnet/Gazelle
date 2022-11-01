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
                'torrentId'   => $torrent->id(),
                'groupId'     => $torrent->groupId(),
                'artist'      => array_filter($torrent->group()->artistRole()->legacyList(), fn($role) => $role), // filter NULLs
                'groupName'   => $torrent->group()->name(),
                'groupYear'   => $torrent->group()->year(),
                'downloadUrl' => "torrents.php?action=download&id={$torrent->id()}&torrent_pass={$this->user->announceKey()}",
            ], $this->better->list(50, 0)
        );
    }
}
