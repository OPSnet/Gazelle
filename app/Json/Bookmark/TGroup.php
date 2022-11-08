<?php

namespace Gazelle\Json\Bookmark;

class TGroup extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\User\Bookmark   $userBookmark,
        protected \Gazelle\Manager\TGroup  $tgMan,
        protected \Gazelle\Manager\Torrent $torMan,
    ) {}

    public function payload(): array {
        $list = [];
        foreach ($this->userBookmark->tgroupBookmarkList() as $bookmark) {
            $tgroup = $this->tgMan->findById($bookmark['tgroup_id']);
            if (is_null($tgroup)) {
                continue;
            }
            $torrentList = [];
            foreach ($tgroup->torrentIdList() as $torrentId) {
                $torrent = $this->torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }
                $torrentList[] = [
                    'id'                      => $torrentId,
                    'groupId'                 => $tgroup->id(),
                    'media'                   => $torrent->media(),
                    'format'                  => $torrent->format(),
                    'encoding'                => $torrent->encoding(),
                    'remasterYear'            => $torrent->remasterYear(),
                    'remastered'              => $torrent->isRemastered(),
                    'remasterTitle'           => $torrent->remasterTitle(),
                    'remasterRecordLabel'     => $torrent->remasterRecordLabel(),
                    'remasterCatalogueNumber' => $torrent->remasterCatalogueNumber(),
                    'scene'                   => $torrent->isScene(),
                    'hasLog'                  => $torrent->hasLog(),
                    'hasCue'                  => $torrent->hasCue(),
                    'logScore'                => $torrent->logScore(),
                    'fileCount'               => $torrent->fileTotal(),
                    'freeTorrent'             => $torrent->isFreeleech(),
                    'size'                    => $torrent->size(),
                    'leechers'                => $torrent->leecherTotal(),
                    'seeders'                 => $torrent->seederTotal(),
                    'snatched'                => $torrent->snatchTotal(),
                    'time'                    => $torrent->uploadDate(),
                    'hasFile'                 => $torrent->hasLogDb(),
                ];
            }
            $list[] = [
                'id'              => $tgroup->id(),
                'name'            => $tgroup->name(),
                'year'            => $tgroup->year(),
                'recordLabel'     => $tgroup->recordLabel(),
                'catalogueNumber' => $tgroup->catalogueNumber(),
                'tagList'         => $tgroup->tagList(),
                'releaseType'     => $tgroup->releaseType(),
                'vanityHouse'     => $tgroup->isShowcase() == 1,
                'image'           => $tgroup->image(),
                'torrents'        => $torrentList,
            ];
        }
        return ['bookmarks' => $list];
    }
}
