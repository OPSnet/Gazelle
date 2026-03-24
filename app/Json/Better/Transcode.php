<?php

namespace Gazelle\Json\Better;

use Gazelle\Search\Transcode as SearchTranscode;

class Transcode extends \Gazelle\Json {
    public function __construct(
        protected string          $announceKey,
        protected SearchTranscode $search,
    ) {}

    public function payload(): array {
        $page = $this->search->page(200, 0);
        shuffle($page);
        $page = array_slice($page, 0, TORRENTS_PER_PAGE);

        $payload = [];
        foreach ($page as $result) {
            $torrent = $result['torrent'];
            $tgroup  = $torrent->group();
            $payload[] = [
                'torrentId'   => $torrent->id,
                'groupId'     => $tgroup->id,
                'artistInfo'  => static::artistPayload($torrent->group()),
                'groupName'   => $tgroup->name(),
                'groupYear'   => $tgroup->year(),
                'downloadUrl' => "torrents.php?action=download&id={$torrent->id}&torrent_pass={$this->announceKey}",
                'source'      => $torrent->location(),
                'missingV0'   => $result['want_v0'] === 1,
                'missing320'  => $result['want_320'] === 1,
            ];
        }
        return $payload;
    }
}
