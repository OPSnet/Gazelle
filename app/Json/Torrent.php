<?php

namespace Gazelle\Json;

class Torrent extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Torrent         $torrent,
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\Torrent $torMan,
    ) {}

    public function torrentPayload(): array {
        $torrent = $this->torrent->setViewer($this->user);
        return array_merge(
            $this->user->snatch()->isSnatched($torrent) || $torrent->uploaderId() == $this->user->id()
                ? [ 'infoHash' => $torrent->infohash() ]
                : [],
            [
                'id'            => $torrent->id(),
                'media'         => $torrent->media(),
                'format'        => $torrent->format(),
                'encoding'      => $torrent->encoding(),
                'remastered'    => $torrent->isRemastered(),
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
                'freeTorrent'   => $torrent->leechType()->value,
                'reported'      => (bool)$torrent->reportTotal($this->user),
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
                ? (new TGroup($this->torrent->group(), $this->user, $this->torMan))->tgroupPayload()
                : null, // an orphan torrent
            'torrent' => $this->torrentPayload(),
        ];
    }
}
