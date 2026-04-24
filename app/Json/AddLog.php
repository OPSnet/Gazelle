<?php

namespace Gazelle\Json;

use Gazelle\Manager\Riplog as RiplogManager;

class AddLog extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Torrent $torrent,
        protected array            $uploadList,
        protected RiplogManager    $riplogManager = new RiplogManager(),
    ) {}

    public function payload(): array {
        $this->torrent->flush();
        $summaryList = [];
        foreach ($this->riplogManager->bulkCreate($this->torrent, $this->uploadList) as $riplog) {
            $summaryList[] = [
                'score'         => $riplog->score(),
                'checksum'      => $riplog->checksumState(),
                'ripper'        => $riplog->ripper(),
                'ripperVersion' => $riplog->ripperVersion(),
                'language'      => $riplog->ripperLanguage(),
                'details'       => $riplog->report(),
            ];
        }
        return [
            'torrentId'         => $this->torrent->id,
            'score'             => $this->torrent->logScore(),
            'checksum'          => $this->torrent->logChecksum(),
            'logcheckerVersion' => $this->riplogManager->logcheckerVersion(),
            'logSummaries'      => $summaryList,
        ];
    }
}
