<?php

namespace Gazelle\Json;

class Torrent extends \Gazelle\Json {
    protected $torrent;
    protected $userId;
    protected $showSnatched = false;

    public function __construct() {
        parent::__construct();
        $this->setMode(JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function setViewerId(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function setShowSnatched(int $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    public function findById(int $id) {
        $this->torrent = (new \Gazelle\Manager\Torrent)->findById($id);
        if (!$this->torrent) {
            $this->failure("bad id parameter");
            return null;
        }
        return $this;
    }

    public function findByInfohash(string $hash) {
        $this->torrent = (new \Gazelle\Manager\Torrent)->findByInfohash($hash);
        if (!$this->torrent) {
            $this->failure("bad hash parameter");
            return null;
        }
        return $this;
    }

    public function payload(): ?array {
        if (!$this->userId) {
            $this->failure('viewer not set');
            return null;
        }

        $this->torrent->setViewerId($this->userId)->setShowSnatched($this->showSnatched);
        $info  = $this->torrent->info();
        $group = $this->torrent->group()->showFallbackImage(false)->info();

        // TODO: implement as a Gazelle class
        global $Categories;
        $categoryName = ($group['CategoryID'] == 0) ? "Unknown" : $Categories[$group['CategoryID'] - 1];

        // Convert file list back to the old format
        $torMan = new \Gazelle\Manager\Torrent;
        foreach ($info['FileList'] as &$file) {
            $file = $torMan->apiFilename($file);
        }
        unset($file);

        return [
            'group' => [
                'wikiBody'        => \Text::full_format($group['WikiBody']),
                'wikiBBcode'      => $group['WikiBody'],
                'wikiImage'       => $group['WikiImage'],
                'id'              => $group['ID'],
                'name'            => $group['Name'],
                'year'            => $group['Year'],
                'recordLabel'     => $group['RecordLabel'] ?? '',
                'catalogueNumber' => $group['CatalogueNumber'] ?? '',
                'releaseType'     => $group['ReleaseType'] ?? '',
                'releaseTypeName' => (new \Gazelle\ReleaseType)->findNameById($group['ReleaseType']),
                'categoryId'      => $group['CategoryID'],
                'categoryName'    => $categoryName,
                'time'            => $group['Time'],
                'vanityHouse'     => $group['VanityHouse'],
                'isBookmarked'    => (new \Gazelle\Bookmark)->isTorrentBookmarked($this->userId, $group['ID']),
                'tags'            => explode('|', $group['tagNames']),
                'musicInfo'       => ($categoryName != "Music")
                    ? null : \Artists::get_artist_by_type($group['ID']),
            ],
            'torrent' => array_merge(
                !is_null($this->torrent->infohash()) || $this->torrent->uploader()->id() == $this->userId
                    ? [ 'infoHash' => $this->torrent->infohash() ]
                    : [],
                [
                    'id'            => $this->torrent->id(),
                    'media'         => $info['Media'],
                    'format'        => $info['Format'],
                    'encoding'      => $info['Encoding'],
                    'remastered'    => $this->torrent->isRemastered(),
                    'remasterYear'  => $info['RemasterYear'],
                    'remasterTitle' => $info['RemasterTitle'],
                    'remasterRecordLabel' => $info['RemasterRecordLabel'],
                    'remasterCatalogueNumber' => $info['RemasterCatalogueNumber'],
                    'scene'         => $info['Scene'],
                    'hasLog'        => $info['HasLog'],
                    'hasCue'        => $info['HasCue'],
                    'logScore'      => $info['LogScore'],
                    'logChecksum'   => $info['LogChecksum'],
                    'logCount'      => $info['LogCount'],
                    'ripLogIds'     => $info['ripLogIds'],
                    'fileCount'     => $info['FileCount'],
                    'size'          => $info['Size'],
                    'seeders'       => $info['Seeders'],
                    'leechers'      => $info['Leechers'],
                    'snatched'      => $info['Snatched'],
                    'freeTorrent'   => $info['FreeTorrent'],
                    'reported'      => count(\Torrents::get_reports($this->torrent->id())) > 0,
                    'time'          => $info['Time'],
                    'description'   => $info['Description'],
                    'fileList'      => implode('|||', $info['FileList']),
                    'filePath'      => $info['FilePath'],
                    'userId'        => $info['UserID'],
                    'username'      => $this->torrent->uploader()->username(),
                ]
            ),
        ];
    }
}
