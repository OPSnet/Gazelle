<?php

namespace Gazelle\Json\Better;

class Transcode extends \Gazelle\Json {
    public function __construct(
        protected string                    $announceKey,
        protected \Gazelle\Search\Transcode $search,
    ) {}

    public function payload(): array {
        $list = $this->search->list(200, 0);
        shuffle($list);
        $list = array_slice($list, 0, TORRENTS_PER_PAGE);

        $payload = [];
        foreach ($list as $result) {
            $torrent = $result['torrent'];
            $tgroup  = $torrent->group();
            $payload[] = [
                'torrentId'   => $torrent->id(),
                'groupId'     => $tgroup->id(),
                'artist'      => $tgroup->artistRole()?->text(),
                'groupName'   => $tgroup->name(),
                'groupYear'   => $tgroup->year(),
                'missingV0'   => $result['want_v0'] === 1,
                'missing320'  => $result['want_320'] === 1,
                'downloadUrl' => "torrents.php?action=download&id={$torrent->id()}&torrent_pass={$this->announceKey}",
                'source'      => $torrent->location(),
            ];
        }
        return $payload;
    }
}
