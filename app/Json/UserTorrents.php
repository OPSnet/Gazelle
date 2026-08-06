<?php

namespace Gazelle\Json;

use Gazelle\Enum\UserTorrentSearch;
use Gazelle\Manager\Torrent as TorrentManager;
use Gazelle\Search\UserTorrent;
use Gazelle\User;

class UserTorrents extends \Gazelle\Json {
    public function __construct(
        protected User              $user,
        protected User              $viewer,
        protected UserTorrentSearch $type,
        protected int               $limit,
        protected int               $offset,
        protected TorrentManager    $torMan = new TorrentManager(),
    ) {}

    public function payload(): array {
        $userTorrent = new UserTorrent(
            $this->user,
            $this->viewer,
            $this->type,
            $this->limit,
            $this->offset,
        );
        $result = [];
        foreach ($userTorrent->torrentList() as $torrent) {
            $tgroup = $torrent->group();
            $item = [
                'groupId'     => $tgroup->id,
                'torrentId'   => $torrent->id,
                'name'        => $tgroup->name(),
                'torrentSize' => $torrent->size(),
            ];
            if ($tgroup->hasArtistRole()) {
                // artistId and artistName are returned mostly for compatibility with RED
                // as people should probably just use `artists` array principally.
                $item['artistId']   = $tgroup->artistRole()?->primaryId();
                $item['artistName'] = $tgroup->artistName();
                $item['artists']    = static::artistPayload($tgroup);
            }
            $result[] = $item;
        }
        return [
            "{$this->type->value}" => $result,
            'total'                => $userTorrent->total(),
        ];
    }
}
