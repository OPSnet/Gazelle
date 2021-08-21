<?php

namespace Gazelle\Json;

use Gazelle\File\RipLog;
use Gazelle\File\RipLogHTML;
use Gazelle\Manager\Torrent;
use Gazelle\Logfile;
use Gazelle\LogfileSummary;
use OrpheusNET\Logchecker\Logchecker;

class AddLog extends \Gazelle\Json {
    /** @var \Gazelle\Torrent */
    protected $torrent;
    protected $userId;
    protected $showSnatched = false;
    protected $files;

    public function __construct() {
        parent::__construct();
        $this->setMode(JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function setViewerId(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function setLogFiles(array $files) {
        $this->files = $files;
        return $this;
    }

    public function findTorrentById(int $id) {
        $this->torrent = (new Torrent())->findById($id);
        if (!$this->torrent) {
            $this->failure("bad id parameter");
            return null;
        }
        return $this;
    }

    public function payload(): ?array {
        if (!$this->userId) {
            $this->failure('viewer not set');
            return null;
        }

        if ($this->userId !== $this->torrent->info()['UserID'] && !check_perms('users_mod')) {
            $this->failure('Not the torrent owner or moderator');
            return null;
        }

        $logfileSummary = new LogfileSummary();
        $ripFiler = new RipLog();
        $htmlFiler = new RipLogHTML();

        $torrentId = $this->torrent->id();
        $groupId = $this->torrent->info()['GroupID'];

        $count = count($this->files['name']);

        $logSummaries = [];

        for ($i = 0; $i < $count; $i++) {
            if (!$this->files['size'][$i]) {
                continue;
            }
            $logfile = new Logfile(
                $this->files['tmp_name'][$i],
                $this->files['name'][$i]
            );
            $logfiles[] = $logfile;
            $logfileSummary->add($logfile);

            $this->db->prepared_query('
                INSERT INTO torrents_logs
                       (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Details)
                VALUES (?,         ?,      ?,         ?,        ?,      ?,              ?,         ?,             ?,                 ?)
                ', $torrentId, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(), $logfile->ripper(),
                    $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
                    Logchecker::getLogcheckerVersion(), $logfile->detailsAsString()
            );
            $logId = $this->db->inserted_id();
            $ripFiler->put($logfile->filepath(), [$torrentId, $logId]);
            $htmlFiler->put($logfile->text(), [$torrentId, $logId]);

            $logSummaries[] = [
                'score' => $logfile->score(),
                'checksum' => $logfile->checksumState(),
                'ripper' => $logfile->ripper(),
                'ripperVersion' => $logfile->ripperVersion(),
                'language' => $logfile->language(),
                'details' => $logfile->detailsAsString()
            ];
        }


        [$score, $checksum] = $this->db->row("
            SELECT min(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
                min(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
            FROM torrents_logs
            WHERE TorrentID = ?
            GROUP BY TorrentID
            ", $torrentId
        );

        $this->db->prepared_query(
            'UPDATE torrents SET LogScore = ?, LogChecksum = ?, HasLogDB = ? WHERE ID = ?',
            $score, $checksum, '1', $torrentId
        );

        $this->cache->deleteMulti([
            "torrent_group_{$groupId}",
            "torrents_details_{$groupId}",
            "tg_{$groupId}",
            "tlist_{$groupId}"
        ]);

        return [
            'torrentId' => $torrentId,
            'score' => $score,
            'checksum' => $checksum,
            'logcheckerVersion' => Logchecker::getLogcheckerVersion(),
            'logSummaries' => $logSummaries
        ];
    }
}
