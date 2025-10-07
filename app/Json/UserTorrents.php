<?php

namespace Gazelle\Json;

use Gazelle\Search\UserTorrent;
use Gazelle\Enum\UserTorrentSearch;

class UserTorrents extends \Gazelle\Json {
    protected int $limit = 500;
    protected int $offset = 0;
    protected string $type = 'seeding';

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\User $viewer,
        protected \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent(),
    ) {}

    public function setType(string $type): static {
        $this->type = $type;
        return $this;
    }

    public function setLimit(int $limit): static {
        $this->limit = $limit;
        return $this;
    }

    public function setOffset(int $offset): static {
        $this->offset = $offset;
        return $this;
    }

    public function payload(): array {
        switch ($this->type) {
            case 'downloaded':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::downloaded);
                break;
            case 'leeching':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::leeching);
                break;
            case 'seeding':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::seeding);
                break;
            case 'snatched':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::snatched);
                break;
            case 'snatched-unseeded':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::snatchedUnseeded);
                break;
            case 'uploaded':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::uploaded);
                break;
            case 'uploaded-unseeded':
                $userTorrent = new UserTorrent($this->user, UserTorrentSearch::uploadedUnseeded);
                break;
            default:
                json_error("bad type");
        }
        $userTorrent->setLimit($this->limit)->setOffset($this->offset);

        return [
            "$this->type" => array_reduce(
                $userTorrent->idList(),
                function ($acc, $id) {
                    $torrent = $this->torMan->findById($id);
                    if ($torrent) {
                        $tgroup = $torrent->group();
                        $item = [
                            'groupId'     => $torrent->groupId(),
                            'torrentId'   => $torrent->id,
                            'name'        => $tgroup->name(),
                            'torrentSize' => $torrent->size(),
                        ];

                        if ($torrent->group()->hasArtistRole()) {
                            // artistId and artistName are returned mostly for compatibility with RED
                            // as people should probably just use `artists` array principally.
                            $item['artistId']   = $tgroup->artistRole()?->primaryId();
                            $item['artistName'] = $tgroup->artistName();
                            $item['artists']    = static::artistPayload($tgroup);
                        }
                        $acc[] = $item;
                    }
                    return $acc;
                },
                [],
            ),
            'total' => $userTorrent->total(),
        ];
    }
}
