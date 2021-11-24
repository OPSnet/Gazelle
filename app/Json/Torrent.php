<?php

namespace Gazelle\Json;

class Torrent extends \Gazelle\Json {
    protected \Gazelle\Torrent $torrent;
    protected \Gazelle\User $user;
    protected bool $showSnatched = false;

    public function setTorrent(\Gazelle\Torrent $torrent) {
        $this->torrent = $torrent;
        return $this;
    }

    public function setViewer(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function setShowSnatched(bool $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    public function torrentPayload(): array {
        $torMan  = new \Gazelle\Manager\Torrent;
        $torrent = $this->torrent->setViewer($this->user)->setShowSnatched($this->showSnatched);
        return array_merge(
            $torrent->isSnatched($this->user->id()) || $torrent->uploaderId() == $this->user->id()
                ? [ 'infoHash' => $torrent->infohash() ]
                : [],
            [
                'id'            => $torrent->id(),
                'media'         => $torrent->media(),
                'format'        => $torrent->format(),
                'encoding'      => $torrent->encoding(),
                'remastered'    => $torrent->isRemastered(),
                'remasterYear'  => $torrent->remasterYear(),
                'remasterTitle' => $torrent->remasterTitle(),
                'remasterRecordLabel'
                                => $torrent->remasterRecordLabel(),
                'remasterCatalogueNumber'
                                => $torrent->remasterCatalogueNumber(),
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
                'freeTorrent'   => $torrent->freeleechStatus(),
                'reported'      => $torMan->hasReport($this->user, $torrent->id()),
                'time'          => $torrent->uploadDate(),
                'description'   => $torrent->description(),
                'fileList'      => implode('|||',
                    array_map(function ($f) use ($torMan) {return $torMan->apiFilename($f);}, $torrent->filelist())
                ),
                'filePath'      => $torrent->path(),
                'userId'        => $torrent->uploaderId(),
                'username'      => $torrent->uploader()->username(),
            ]
        );
    }

    public function payload(): ?array {
        if (!isset($this->torrent)) {
            $this->failure('torrent not set');
            return null;
        }
        if (!isset($this->user)) {
            $this->failure('viewer not set');
            return null;
        }

        return [
            'group' => $this->torrent->hasTGroup()
                ? (new TGroup)
                    ->setViewer($this->user)
                    ->setTGroup($this->torrent->group())
                    ->tgroupPayload()
                : null, // an orphan torrent
            'torrent' => $this->torrentPayload(),
        ];
    }
}
