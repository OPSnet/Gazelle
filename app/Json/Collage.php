<?php

namespace Gazelle\Json;

class Collage extends \Gazelle\Json {
    protected \Gazelle\Collage         $collage;
    protected \Gazelle\Manager\TGroup  $tgMan;
    protected \Gazelle\Manager\Torrent $torMan;
    protected \Gazelle\User            $user;

    public function setCollage(\Gazelle\Collage $collage) {
        $this->collage = $collage;
        return $this;
    }

    public function setTGroupManager(\Gazelle\Manager\TGroup $tgMan) {
        $this->tgMan = $tgMan;
        return $this;
    }

    public function setTorrentManager(\Gazelle\Manager\Torrent $torMan) {
        $this->torMan = $torMan;
        return $this;
    }

    public function setUser(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function artistPayload(): array {
        return $this->collage->nameList();
    }

    public function tgroupPayload(): array {
        $entryList = $this->collage->entryList();
        $payload = [];
        foreach ($entryList as $tgroupId) {
            $tgroup = $this->tgMan->findByid($tgroupId);
            if (is_null($tgroup)) {
                continue;
            }
            $idList = $tgroup->torrentIdList();
            $torrentList = [];
            foreach ($idList as $torrentId) {
                $torrent = $this->torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }
                $torrentList[] = [
                    'torrentid'               => $torrentId,
                    'media'                   => $torrent->media(),
                    'format'                  => $torrent->format(),
                    'encoding'                => $torrent->encoding(),
                    'remastered'              => $torrent->isRemastered(),
                    'remasterYear'            => $torrent->remasterYear(),
                    'remasterTitle'           => $torrent->remasterTitle(),
                    'remasterRecordLabel'     => $torrent->remasterRecordLabel(),
                    'remasterCatalogueNumber' => $torrent->remasterCatalogueNumber(),
                    'scene'                   => $torrent->isScene(),
                    'hasLog'                  => $torrent->hasLog(),
                    'hasCue'                  => $torrent->hasCue(),
                    'logScore'                => $torrent->logScore(),
                    'fileCount'               => $torrent->fileTotal(),
                    'size'                    => $torrent->size(),
                    'seeders'                 => $torrent->seederTotal(),
                    'leechers'                => $torrent->leecherTotal(),
                    'snatched'                => $torrent->snatchTotal(),
                    'freeTorrent'             => $torrent->isFreeleech(),
                    'reported'                => $this->torMan->hasReport($this->user, $torrentId),
                    'time'                    => $torrent->uploadDate(),
                ];
            }
            $payload[] = [
                'id'              => $tgroupId,
                'name'            => $tgroup->name(),
                'year'            => $tgroup->year(),
                'categoryId'      => $tgroup->categoryId(),
                'recordLabel'     => $tgroup->recordLabel(),
                'catalogueNumber' => $tgroup->catalogueNumber(),
                'vanityHouse'     => $tgroup->isShowcase(),
                'tagList'         => array_values($tgroup->tagNameList()),
                'releaseType'     => $tgroup->releaseType(),
                'wikiImage'       => $tgroup->image(),
                'musicInfo'       => $tgroup->categoryName() == 'Music' ? $tgroup->artistRole()->roleListByType() : null,
                'torrents'        => $torrentList,
            ];
        }
        return $payload;
    }

    public function payload(): ?array {
        if (!isset($this->collage)) {
            $this->failure('collage not set');
            return null;
        }
        if (!isset($this->tgMan)) {
            $this->failure('torrent group manager not set');
            return null;
        }
        if (!isset($this->torMan)) {
            $this->failure('torrent manager not set');
            return null;
        }
        if (!isset($this->user)) {
            $this->failure('user not set');
            return null;
        }

        return array_merge(
            [
                'id'                  => $this->collage->id(),
                'name'                => $this->collage->name(),
                'description'         => \Text::full_format($this->collage->description()),
                'description_raw'     => $this->collage->description(),
                'creatorID'           => $this->collage->ownerid(),
                'deleted'             => $this->collage->isDeleted(),
                'collageCategoryID'   => $this->collage->categoryId(),
                'collageCategoryName' => COLLAGE[$this->collage->categoryId()],
                'locked'              => $this->collage->isLocked(),
                'maxGroups'           => $this->collage->maxGroups(),
                'maxGroupsPerUser'    => $this->collage->maxGroupsPerUser(),
                'hasBookmarked'       => (new \Gazelle\Bookmark($this->user))->isCollageBookmarked($this->collage->id()),
                'subscriberCount'     => $this->collage->numSubscribers(),
                'torrentGroupIDList'  => $this->collage->entryList(),
            ],
            $this->collage->isArtist()
                ? ['artists'       => $this->artistPayload()]
                : ['torrentgroups' => $this->tgroupPayload()]
        );
    }
}
