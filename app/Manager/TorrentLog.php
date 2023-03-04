<?php

namespace Gazelle\Manager;

class TorrentLog extends \Gazelle\Base {
    public function __construct(
        protected \Gazelle\File\RipLog     $ripFiler,
        protected \Gazelle\File\RipLogHTML $htmlFiler,
    ) {}

    public function create(\Gazelle\Torrent $torrent, \Gazelle\Logfile $logfile, string $checkerVersion): \Gazelle\TorrentLog {
        self::$db->prepared_query('
            INSERT INTO torrents_logs
                   (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, Details, LogcheckerVersion)
            VALUES (?,         ?,      ?,         ?,        ?,      ?,             ?,          ?,             ?,       ?)
            ', $torrent->id(), $logfile->score(), $logfile->checksumStatus(), $logfile->filename(),
                $logfile->ripper(), $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(), $logfile->detailsAsString(),
                $checkerVersion
        );
        $logId = self::$db->inserted_id();
        $this->ripFiler->put($logfile->filepath(), [$torrent->id(), $logId]);
        $this->htmlFiler->put($logfile->text(),    [$torrent->id(), $logId]);
        return $this->findById($torrent, $logId);
    }

    public function findById(\Gazelle\Torrent $torrent, int $logId): ?\Gazelle\TorrentLog {
        $id = (int)self::$db->scalar("
            SELECT LogID FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $torrent->id(), $logId
        );
        return $id ? new \Gazelle\TorrentLog($torrent, $id) : null;
    }
}
