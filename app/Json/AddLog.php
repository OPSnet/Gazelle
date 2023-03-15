<?php

namespace Gazelle\Json;

use OrpheusNET\Logchecker\Logchecker;

class AddLog extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Torrent            $torrent,
        protected \Gazelle\User               $user,
        protected \Gazelle\Manager\TorrentLog $torrentLogManager,
        protected \Gazelle\LogfileSummary     $logfileSummary,
    ) {}

    public function payload(): array {
        if ($this->user->id() !== $this->torrent->uploaderId() && !$this->user->permitted('admin_add_log')) {
            $this->failure('Not the torrent owner or moderator');
            return [];
        }

        $logSummaries = [];
        $checkerVersion = Logchecker::getLogcheckerVersion();
        foreach($this->logfileSummary->all() as $logfile) {
            $this->torrentLogManager->create($this->torrent, $logfile, $checkerVersion);
            $logSummaries[] = [
                'score'         => $logfile->score(),
                'checksum'      => $logfile->checksumState(),
                'ripper'        => $logfile->ripper(),
                'ripperVersion' => $logfile->ripperVersion(),
                'language'      => $logfile->language(),
                'details'       => $logfile->detailsAsString(),
            ];
        }
        $this->torrent->modifyLogscore();
        $this->torrent->flush();

        return [
            'torrentId'         => $this->torrent->id(),
            'score'             => $this->torrent->logScore(),
            'checksum'          => $this->torrent->logChecksum(),
            'logcheckerVersion' => $checkerVersion,
            'logSummaries'      => $logSummaries,
        ];
    }
}
