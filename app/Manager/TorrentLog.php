<?php

namespace Gazelle\Manager;

class TorrentLog extends \Gazelle\Base {

    public function findById(\Gazelle\Torrent $torrent, int $logId): ?\Gazelle\TorrentLog {
        $id = self::$db->scalar("
            SELECT LogID FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $torrent->id(), $logId
        );
        return $id ? new \Gazelle\TorrentLog($torrent, $id) : null;
    }
}
