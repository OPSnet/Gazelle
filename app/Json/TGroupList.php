<?php

namespace Gazelle\Json;

class TGroupList extends \Gazelle\Json {

    public function __construct(
        protected \Gazelle\User\Bookmark   $bookmark,
        protected \Gazelle\User\Snatch     $snatcher,
        protected \Gazelle\Manager\TGroup  $tgMan,
        protected \Gazelle\Manager\Torrent $torMan,
        protected array $result,
        protected bool  $groupResults,
        protected int   $total,
        protected int   $page
    ) { }

    public function payload(): array {
        $list = [];
        foreach ($this->result as $tgroupId) {
            $tgroup = $this->tgMan->findById($tgroupId);
            if (is_null($tgroup)) {
                continue;
            }

            $torrentIdList = $tgroup->torrentIdList();

            if ($this->groupResults && (count($torrentIdList) > 1 || $tgroup->categoryGrouped())) {
                $prev = false;
                $EditionID = 0;
                unset($FirstUnknown);

                $groupList = [];
                foreach ($torrentIdList as $torrentId) {
                    $torrent = $this->torMan->findById($torrentId);
                    if (is_null($torrent)) {
                        continue;
                    }

                    $current = $torrent->remasterTuple();
                    if ($torrent->isRemasteredUnknown()) {
                        $FirstUnknown = !isset($FirstUnknown);
                    }
                    if ($tgroup->categoryGrouped() && ($prev != $current || (isset($FirstUnknown) && $FirstUnknown))) {
                        $EditionID++;
                    }
                    $prev = $current;

                    $groupList[] = [
                        'torrentId'               => $torrent->id(),
                        'editionId'               => $EditionID,
                        'artists'                 => (new \Gazelle\ArtistRole\TGroup($tgroup->id()))->rolelist()['main'],
                        'remastered'              => $torrent->isRemastered(),
                        'remasterYear'            => $torrent->remasterYear(),
                        'remasterRecordLabel'     => $torrent->remasterRecordLabel() ?? '',
                        'remasterCatalogueNumber' => $torrent->remasterCatalogueNumber() ?? '',
                        'remasterTitle'           => $torrent->remasterTitle() ?? '',
                        'media'                   => $torrent->media(),
                        'format'                  => $torrent->format(),
                        'encoding'                => $torrent->encoding(),
                        'hasLog'                  => $torrent->hasLog(),
                        'logScore'                => $torrent->logScore(),
                        'hasCue'                  => $torrent->hasCue(),
                        'scene'                   => $torrent->isScene(),
                        'vanityHouse'             => $tgroup->isShowcase(),
                        'fileCount'               => $torrent->fileTotal(),
                        'time'                    => $torrent->uploadDate(),
                        'size'                    => $torrent->size(),
                        'snatches'                => $torrent->snatchTotal(),
                        'seeders'                 => $torrent->seederTotal(),
                        'leechers'                => $torrent->leecherTotal(),
                        'isFreeleech'             => $torrent->isFreeleech(),
                        'isNeutralLeech'          => $torrent->isNeutralLeech(),
                        'isPersonalFreeleech'     => $torrent->isFreeleechPersonal(),
                        'canUseToken'             => $this->snatcher->user()->canSpendFLToken($torrent),
                        'hasSnatched'             => $this->snatcher->showSnatch($torrent->id()),
                    ];
                }

                $list[] = [
                    'groupId'       => $tgroupId,
                    'groupName'     => $tgroup->name(),
                    'artist'        => $tgroup->artistName(),
                    'cover'         => $tgroup->image(),
                    'tags'          => array_values($tgroup->tagNameList()),
                    'bookmarked'    => $this->bookmark->isTorrentBookmarked($tgroup->id()),
                    'vanityHouse'   => $tgroup->isShowcase(),
                    'groupYear'     => $tgroup->year(),
                    'releaseType'   => $tgroup->releaseTypeName() ?? '',
                    'groupTime'     => $tgroup->mostRecentUpload(),
                    'maxSize'       => $tgroup->maxTorrentSize(),
                    'totalSnatched' => $tgroup->stats()->snatchTotal(),
                    'totalSeeders'  => $tgroup->stats()->seedingTotal(),
                    'totalLeechers' => $tgroup->stats()->leechTotal(),
                    'torrents'      => $groupList,
                ];
            } else {
                // Viewing a type that does not require grouping
                $torrent = $this->torMan->findById(current($torrentIdList));
                if (is_null($torrent)) {
                    continue;
                }
                $list[] = [
                    'groupId'             => $tgroupId,
                    'groupName'           => $tgroup->name(),
                    'torrentId'           => $torrent->id(),
                    'tags'                => array_values($tgroup->tagNameList()),
                    'category'            => $tgroup->categoryName(),
                    'fileCount'           => $torrent->fileTotal(),
                    'groupTime'           => $torrent->uploadDate(),
                    'size'                => $torrent->size(),
                    'snatches'            => $torrent->snatchTotal(),
                    'seeders'             => $torrent->seederTotal(),
                    'leechers'            => $torrent->leecherTotal(),
                    'isFreeleech'         => $torrent->isFreeleech(),
                    'isNeutralLeech'      => $torrent->isNeutralLeech(),
                    'isPersonalFreeleech' => $torrent->isFreeleechPersonal(),
                    'canUseToken'         => $this->snatcher->user()->canSpendFLToken($torrent),
                    'hasSnatched'         => $this->snatcher->showSnatch($torrent->id()),
                ];
            }
        }

        return [
            'currentPage' => $this->page,
            'pages'       => (int)ceil($this->page / TORRENTS_PER_PAGE),
            'results'     => $list,
        ];
    }
}
