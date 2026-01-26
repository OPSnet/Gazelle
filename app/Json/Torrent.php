<?php

namespace Gazelle\Json;

use Gazelle\Enum\TorrentFlag;

class Torrent extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Torrent                $torrent,
        protected \Gazelle\User                   $user,
        protected \Gazelle\Manager\Torrent        $torMan    = new \Gazelle\Manager\Torrent(),
        protected \Gazelle\Manager\Torrent\Report $reportMan = new \Gazelle\Manager\Torrent\Report(),
    ) {}

    public function torrentPayload(): array {
        $torrent   = $this->torrent;
        $trumpList = [];
        foreach ($torrent->reportIdList($this->user) as $reportId) {
            $report = $this->reportMan->findById($reportId);
            if ($report) {
                $trumpList[] = $report->name();
            }
        }
        if ($torrent->hasFlag(TorrentFlag::noLineage)) {
            $trumpList[] = 'Missing lineage';
        }

        return array_merge(
            $this->user->snatch()->isSnatched($torrent) || $torrent->uploaderId() == $this->user->id
                ? [ 'infoHash' => $torrent->infohash() ]
                : [],
            [
                'id'            => $torrent->id,
                'media'         => $torrent->media(),
                'format'        => $torrent->format(),
                'encoding'      => $torrent->encoding(),
                'remasterYear'  => $torrent->remasterYear(),
                'remasterTitle' => $torrent->remasterTitle() ?? '',
                'remasterRecordLabel'
                                => $torrent->remasterRecordLabel() ?? '',
                'remasterCatalogueNumber'
                                => $torrent->remasterCatalogueNumber() ?? '',
                'scene'         => $torrent->isScene(),
                'hasLog'        => $torrent->hasLog(),
                'hasCue'        => $torrent->hasCue(),
                'logScore'      => $torrent->logScore(),
                'logChecksum'   => $torrent->logChecksum(),
                'logCount'      => count($torrent->ripLogIdList()),
                'ripLogIds'     => $torrent->ripLogIdList(),
                'fileCount'     => $torrent->fileTotal(),
                'size'          => $torrent->size(),
                'seeders'       => $torrent->seederTotal(),
                'leechers'      => $torrent->leecherTotal(),
                'snatched'      => $torrent->snatchTotal(),
                'freeTorrent'   => $torrent->isFreeleechPersonal() ? 'personal' : $torrent->leechType()->label(),
                'freeReason'    => $torrent->leechReason()->label(),
                'reported'      => (bool)$torrent->reportTotal($this->user),
                'trumpable'     => $trumpList !== [],
                'trumpable_reasons'
                                => array_unique($trumpList),
                'time'          => $torrent->created(),
                'description'   => $torrent->description(),
                'fileList'      => $torrent->fileListLegacyAPI(),
                'filePath'      => $torrent->path(),
                'userId'        => $torrent->uploaderId(),
                'username'      => $torrent->uploader()->username(),
            ]
        );
    }

    public function payload(): array {
        return [
            'group' => $this->torrent->hasTGroup()
                ? new TGroup($this->torrent->group(), $this->user, $this->torMan)->tgroupPayload()
                : null, // an orphan torrent
            'torrent' => $this->torrentPayload(),
        ];
    }
}
