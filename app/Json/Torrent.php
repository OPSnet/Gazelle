<?php

namespace Gazelle\Json;

class Torrent extends \Gazelle\Json {
    protected \Gazelle\Torrent $torrent;
    protected bool $showSnatched = false;
    protected $userId;

    public function __construct() {
        parent::__construct();
        $this->setMode(JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function setViewerId(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function setShowSnatched(bool $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    public function findById(int $id) {
        $torrent = (new \Gazelle\Manager\Torrent)->findById($id);
        if (is_null($torrent)) {
            $this->failure("bad id parameter");
            return null;
        }
        $this->torrent = $torrent;
        return $this;
    }

    public function findByInfohash(string $hash) {
        $torrent = (new \Gazelle\Manager\Torrent)->findByInfohash($hash);
        if (is_null($torrent)) {
            $this->failure("bad hash parameter");
            return null;
        }
        $this->torrent = $torrent;
        return $this;
    }

    public function payload(): ?array {
        if (!$this->userId) {
            $this->failure('viewer not set');
            return null;
        }

        $torrent = $this->torrent->setViewerId($this->userId)->setShowSnatched($this->showSnatched);
        $group = $torrent->group();

        // TODO: implement as a Gazelle class
        $categoryName = $group->categoryId() == 0 ? "Unknown" : CATEGORY[$group->categoryId() - 1];

        $torMan = new \Gazelle\Manager\Torrent;
        return [
            'group' => [
                'wikiBody'        => \Text::full_format($group->description()),
                'wikiBBcode'      => $group->description(),
                'wikiImage'       => $group->image(),
                'id'              => $group->id(),
                'name'            => $group->name(),
                'year'            => $group->year(),
                'recordLabel'     => $group->recordLabel() ?? '',
                'catalogueNumber' => $group->catalogueNumber() ?? '',
                'releaseType'     => $group->releaseType() ?? '',
                'releaseTypeName' => (new \Gazelle\ReleaseType)->findNameById($group->releaseType()),
                'categoryId'      => $group->categoryId(),
                'categoryName'    => $categoryName,
                'time'            => $group->time(),
                'vanityHouse'     => $group->isShowcase(),
                'isBookmarked'    => (new \Gazelle\Bookmark)->isTorrentBookmarked($this->userId, $group->id()),
                'tags'            => $group->tagNameList(),
                'musicInfo'       => ($categoryName != "Music")
                    ? null : \Artists::get_artist_by_type($group->id()),
            ],
            'torrent' => array_merge(
                !is_null($torrent->infohash()) || $torrent->uploaderId() == $this->userId
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
                    'reported'      => $torMan->hasReport($torrent->id()),
                    'time'          => $torrent->uploadDate(),
                    'description'   => $torrent->description(),
                    'fileList'      => implode('|||',
                        array_map(function ($f) use ($torMan) {return $torMan->apiFilename($f);}, $torrent->filelist())
                    ),
                    'filePath'      => $torrent->path(),
                    'userId'        => $torrent->uploaderId(),
                    'username'      => $torrent->uploader()->username(),
                ]
            ),
        ];
    }
}
