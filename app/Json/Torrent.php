<?php

namespace Gazelle\Json;

class Torrent extends \Gazelle\Json {
    protected $id;
    protected $infohash;
    protected $userId;
    protected $showSnatched;

    public function __construct() {
        parent::__construct();
        $this->setMode(JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function setViewer(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function setShowSnatched(int $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    public function setId(int $id) {
        if (!$id) {
            $this->failure("bad id parameter");
            return null;
        }
        $this->id = $id;
        $this->infohash = null;
        return $this;
    }

    public function setIdFromHash(string $hash) {
        $torMan = new \Gazelle\Manager\Torrent;
        if (!$torMan->isValidHash($hash)) {
            $this->failure("bad hash parameter");
            return null;
        } else {
            $this->id = $torMan->hashToTorrentId($hash);
            if (!$this->id) {
                $this->failure("bad hash parameter");
                return null;
            }
        }
        $this->infohash = $hash;
        return $this;
    }

    public function payload(): ?array {
        if (!$this->userId) {
            $this->failure('viewer not set');
            return null;
        }

        [$details, $torrent] = (new \Gazelle\Manager\Torrent)
            ->setTorrentId($this->id)
            ->setViewer($this->userId)
            ->setShowSnatched($this->showSnatched ?? 0)
            ->showFallbackImage(false)
            ->torrentInfo();
        if (!$details) {
            $this->failure("bad id parameter");
            return null;
        }
        $groupID = $details['ID'];

        // TODO: implement as a Gazelle class
        global $Categories;
        $categoryName = ($details['CategoryID'] == 0) ? "Unknown" : $Categories[$details['CategoryID'] - 1];

        // Convert file list back to the old format
        $fileList = explode("\n", $torrent['FileList']);
        foreach ($fileList as &$file) {
            $file = \Torrents::filelist_old_format($file);
        }

        $uploader = (new \Gazelle\Manager\User)->findById($torrent['UserID']);
        $username = $uploader ? $uploader->username() : '';

        return [
            'group' => [
                'wikiBody'        => \Text::full_format($details['WikiBody']),
                'wikiBBcode'      => $details['WikiBody'],
                'wikiImage'       => $details['WikiImage'],
                'id'              => $details['ID'],
                'name'            => $details['Name'],
                'year'            => $details['Year'],
                'recordLabel'     => $details['RecordLabel'] ?? '',
                'catalogueNumber' => $details['CatalogueNumber'] ?? '',
                'releaseType'     => $details['ReleaseType'] ?? '',
                'releaseTypeName' => (new \Gazelle\ReleaseType)->findNameById($details['ReleaseType']),
                'categoryId'      => $details['CategoryID'],
                'categoryName'    => $categoryName,
                'time'            => $details['Time'],
                'vanityHouse'     => $details['VanityHouse'],
                'isBookmarked'    => (new \Gazelle\Bookmark)->isTorrentBookmarked($this->userId, $groupID),
                'tags'            => explode('|', $details['tagNames']),
                'musicInfo'       => ($categoryName != "Music")
                    ? null : \Artists::get_artist_by_type($groupID),
            ],
            'torrent' => array_merge(
                !is_null($this->infohash) || $torrent['UserID'] == $this->userId
                    ? [ 'infoHash' => $torrent['InfoHash'] ]
                    : [],
                [
                    'id'            => $torrent['ID'],
                    'media'         => $torrent['Media'],
                    'format'        => $torrent['Format'],
                    'encoding'      => $torrent['Encoding'],
                    'remastered'    => $torrent['Remastered'] == 1,
                    'remasterYear'  => (int)$torrent['RemasterYear'],
                    'remasterTitle' => $torrent['RemasterTitle'] ?? '',
                    'remasterRecordLabel' => $torrent['RemasterRecordLabel'] ?? '',
                    'remasterCatalogueNumber' => $torrent['RemasterCatalogueNumber'] ?? '',
                    'scene'         => $torrent['Scene'],
                    'hasLog'        => $torrent['HasLog'],
                    'hasCue'        => $torrent['HasCue'],
                    'logScore'      => $torrent['LogScore'],
                    'logChecksum'   => $torrent['LogChecksum'],
                    'logCount'      => $torrent['LogCount'],
                    'ripLogIds'     => $torrent['ripLogIds'],
                    'fileCount'     => $torrent['FileCount'],
                    'size'          => $torrent['Size'],
                    'seeders'       => $torrent['Seeders'],
                    'leechers'      => $torrent['Leechers'],
                    'snatched'      => $torrent['Snatched'],
                    'freeTorrent'   => $torrent['FreeTorrent'],
                    'reported'      => count(\Torrents::get_reports($this->id)) > 0,
                    'time'          => $torrent['Time'],
                    'description'   => $torrent['Description'],
                    'fileList'      => implode('|||', $fileList),
                    'filePath'      => $torrent['FilePath'],
                    'userId'        => $torrent['UserID'],
                    'username'      => $username,
                ]
            ),
        ];
    }
}
