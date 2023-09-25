<?php

namespace Gazelle\Json;

class Artist extends \Gazelle\Json {
    protected bool $releasesOnly = false;

    public function __construct(
        protected \Gazelle\Artist          $artist,
        protected \Gazelle\User            $user,
        protected \Gazelle\User\Bookmark   $bookmark,
        protected \Gazelle\Manager\Request $requestMan,
        protected \Gazelle\Manager\TGroup  $tgMan,
        protected \Gazelle\Manager\Torrent $torMan
    ) {}

    public function setReleasesOnly(bool $releasesOnly): static {
        $this->releasesOnly = $releasesOnly;
        return $this;
    }

    public function payload(): array {
        $artist   = $this->artist;
        $artistId = $artist->id();
        $artist->loadArtistRole();
        $GroupIDs = $artist->groupIds();

        $JsonTorrents = [];
        $Tags = [];

        foreach ($GroupIDs as $GroupID) {
            $tgroup = $this->tgMan->findById($GroupID);
            if (is_null($tgroup)) {
                continue;
            }
            $artists = $tgroup->artistRole()->legacyList();
            $artists = $artists[1] ?? null;
            $Found = $this->search_array($artists, 'id', $artistId);
            if ($this->releasesOnly && empty($Found)) {
                continue;
            }

            $tagList = $tgroup->tagNameList();
            foreach ($tagList as $tag) {
                if (!isset($Tags[$tag])) {
                    $Tags[$tag] = ['name' => $tag, 'count' => 0];
                }
                $Tags[$tag]['count']++;
            }

            $torrentIds = $tgroup->torrentIdList();
            $InnerTorrents = [];
            foreach ($torrentIds as $torrentId) {
                $torrent = $this->torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }

                $InnerTorrents[] = [
                    'id'                   => $torrent->id(),
                    'groupId'              => $GroupID,
                    'media'                => $torrent->media(),
                    'format'               => $torrent->format(),
                    'encoding'             => $torrent->encoding(),
                    'remasterYear'         => (int)$torrent->remasterYear(),
                    'remastered'           => $torrent->isRemastered(),
                    'remasterTitle'        => (string)$torrent->remasterTitle(),
                    'remasterRecordLabel'  => (string)$torrent->remasterRecordLabel(),
                    'scene'                => $torrent->isScene(),
                    'hasLog'               => $torrent->hasLog(),
                    'hasCue'               => $torrent->hasCue(),
                    'logScore'             => $torrent->logScore(),
                    'fileCount'            => $torrent->fileTotal(),
                    'freeTorrent'          => $torrent->isFreeleech(),
                    'size'                 => $torrent->size(),
                    'leechers'             => $torrent->leecherTotal(),
                    'seeders'              => $torrent->seederTotal(),
                    'snatched'             => $torrent->snatchTotal(),
                    'time'                 => $torrent->created(),
                    'hasFile'              => $torrent->id(), /* legacy wtf */
                ];
            }
            $JsonTorrents[] = [
                'groupId'              => $GroupID,
                'groupName'            => $tgroup->name(),
                'groupYear'            => $tgroup->year(),
                'groupRecordLabel'     => $tgroup->recordLabel(),
                'groupCatalogueNumber' => $tgroup->catalogueNumber(),
                'groupCategoryID'      => $tgroup->categoryId(),
                'tags'                 => $tagList,
                'releaseType'          => (int)$tgroup->releaseType(),
                'wikiImage'            => $tgroup->image(),
                'groupVanityHouse'     => $tgroup->isShowcase(),
                'hasBookmarked'        => $this->bookmark->isTorrentBookmarked($GroupID),
                'artists'              => $artists,
                'extendedArtists'      => $tgroup->artistRole()->legacyList(),
                'torrent'              => $InnerTorrents,
            ];
        }

        $JsonSimilar = [];
        foreach ((new \Gazelle\Artist\Similar($artist))->info() as $s) {
            $JsonSimilar[] = [
                'artistId'  => $s['artist_id'],
                'name'      => $s['name'],
                'score'     => $s['score'],
                'similarId' => $s['similar_id']
            ];
        }

        $requestList = [];
        if (!$this->user->disableRequests()) {
            $requestList = array_map(
                fn ($r) => [
                    'requestId'  => $r->id(),
                    'categoryId' => $r->categoryId(),
                    'title'      => $r->title(),
                    'year'       => $r->year(),
                    'timeAdded'  => $r->created(),
                    'votes'      => $r->userVotedTotal(),
                    'bounty'     => $r->bountyTotal(),
                ], $this->requestMan->findByArtist($artist)
            );
        }

        $stats = $artist->stats();
        return [
            'id'                   => $artistId,
            'name'                 => $artist->name(),
            'notificationsEnabled' => $this->user->hasArtistNotification($artist->name()),
            'hasBookmarked'        => $this->bookmark->isArtistBookmarked($artistId),
            'image'                => $artist->image(),
            'body'                 => \Text::full_format($artist->body()),
            'bodyBbcode'           => $artist->body(),
            'vanityHouse'          => $artist->isShowcase(),
            'tags'                 => array_values($Tags),
            'similarArtists'       => $JsonSimilar,
            'statistics' => [
                'numGroups'   => $stats->tgroupTotal(),
                'numTorrents' => $stats->torrentTotal(),
                'numSeeders'  => $stats->seederTotal(),
                'numLeechers' => $stats->leecherTotal(),
                'numSnatches' => $stats->snatchTotal(),
                'numRequests' => count($requestList),
            ],
            'torrentgroup' => $JsonTorrents,
            'requests'     => $requestList,
        ];
    }

    protected function search_array(mixed $Array, string $Key, mixed $Value): array {
        $results = [];
        if (is_array($Array)) {
            if (isset($Array[$Key]) && $Array[$Key] == $Value) {
                $results[] = $Array;
            }
            foreach ($Array as $subarray) {
                $results = array_merge($results, $this->search_array($subarray, $Key, $Value));
            }
        }
        return $results;
    }
}
