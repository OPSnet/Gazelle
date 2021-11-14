<?php

namespace Gazelle\Json;

use Gazelle\File\RipLog;
use Gazelle\File\RipLogHTML;
use Gazelle\Logfile;
use Gazelle\LogfileSummary;
use OrpheusNET\Logchecker\Logchecker;

class AddLog extends \Gazelle\Json {
    protected \Gazelle\Torrent $torrent;
    protected \Gazelle\User $user;
    protected bool $showSnatched = false;
    protected array $files;

    public function __construct() {
        parent::__construct();
        $this->setMode(JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function setTorrent(\Gazelle\Torrent $torrent) {
        $this->torrent = $torrent;
        return $this;
    }

    public function setViewer(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function setLogFiles(array $files) {
        $this->files = $files;
        return $this;
    }

    public function payload(): ?array {
        if (is_null($this->torrent)) {
            $this->failure('torrent not found');
            return null;
        }
        if (is_null($this->user)) {
            $this->failure('viewer not set');
            return null;
        }
        if ($this->user->id() !== $this->torrent->uploaderId() && !$this->user->permitted('admin_add_log')) {
            $this->failure('Not the torrent owner or moderator');
            return null;
        }

        $logfileSummary = new LogfileSummary;
        $ripFiler = new RipLog;
        $htmlFiler = new RipLogHTML;

        $torrentId    = $this->torrent->id();
        $logSummaries = [];
        for ($i = 0, $total = count($this->files['name']); $i < $total; $i++) {
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
                'score'         => $logfile->score(),
                'checksum'      => $logfile->checksumState(),
                'ripper'        => $logfile->ripper(),
                'ripperVersion' => $logfile->ripperVersion(),
                'language'      => $logfile->language(),
                'details'       => $logfile->detailsAsString()
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

        $groupId = $this->torrent->groupId();
        $this->cache->deleteMulti([
            "torrent_group_{$groupId}",
            "torrents_details_{$groupId}",
            sprintf(\Gazelle\TGroup::CACHE_KEY, $groupId),
            sprintf(\Gazelle\TGroup::CACHE_TLIST_KEY, $groupId),
        ]);

        return [
            'torrentId'         => $torrentId,
            'score'             => $score,
            'checksum'          => $checksum,
            'logcheckerVersion' => Logchecker::getLogcheckerVersion(),
            'logSummaries'      => $logSummaries
        ];
    }
}
