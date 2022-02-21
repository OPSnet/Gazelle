<?php

namespace Gazelle\Json;

class Artist extends \Gazelle\Json {
    protected \Gazelle\Artist          $artist;
    protected \Gazelle\User            $user;
    protected \Gazelle\Manager\TGroup  $tgMan;
    protected \Gazelle\Manager\Torrent $torMan;
    protected bool $releasesOnly = false;

    public function setArtist(\Gazelle\Artist $artist) {
        $this->artist = $artist;
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

    public function setViewer(\Gazelle\User $user) {
        $this->user = $user;
        return $this;
    }

    public function setReleasesOnly(bool $releasesOnly) {
        $this->releasesOnly = $releasesOnly;
        return $this;
    }

    public function payload(): ?array {
        if (!$this->user) {
            $this->failure('viewer not set');
            return null;
        }
        $artistId = $this->artist->id();
        $this->artist->loadArtistRole();
        $GroupIDs = $this->artist->groupIds();

        $JsonTorrents = [];
        $Tags = [];
        $NumTorrents = $NumSeeders = $NumLeechers = $NumSnatches = 0;
        $bookmark = new \Gazelle\Bookmark($this->user);

        foreach ($GroupIDs as $GroupID) {
            $tgroup = $this->tgMan->findById($GroupID);
            if (is_null($tgroup)) {
                continue;
            }
            $artists = $tgroup->artistRole()->legacyList();
            $artists = isset($artists[1]) ? $artists[1] : null;
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
                $NumTorrents++;
                $NumSeeders += $torrent->seederTotal();
                $NumLeechers += $torrent->leecherTotal();
                $NumSnatches += $torrent->snatchTotal();

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
                    'time'                 => $torrent->uploadDate(),
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
                'hasBookmarked'        => $bookmark->isTorrentBookmarked($GroupID),
                'artists'              => $artists,
                'extendedArtists'      => $tgroup->artistRole()->legacyList(),
                'torrent'              => $InnerTorrents,
            ];
        }

        $JsonSimilar = [];
        $similar = $this->artist->similarArtists();
        foreach ($similar as $s) {
            $JsonSimilar[] = [
                'artistId'  => $s['ArtistID'],
                'name'      => $s['Name'],
                'score'     => $s['Score'],
                'similarId' => $s['SimilarID']
            ];
        }

        $JsonRequests = [];
        if (!$this->user->disableRequests()) {
            $Requests = $this->artist->requests();
            foreach ($Requests as $RequestID => $Request) {
                $JsonRequests[] = [
                    'requestId'  => $RequestID,
                    'categoryId' => $Request['CategoryID'],
                    'title'      => $Request['Title'],
                    'year'       => $Request['Year'],
                    'timeAdded'  => $Request['TimeAdded'],
                    'votes'      => $Request['Votes'],
                    'bounty'     => (int)$Request['Bounty']
                ];
            }
        }

        $name = $this->artist->name();
        if (is_null($name)) {
            global $Debug;
            $Debug->analysis("Artist has null name", $artistId, 3600 * 168);
        }
        return [
            'id'             => $artistId,
            'name'           => $name,
            'notificationsEnabled' =>
                is_null($name) ? false : $this->user->hasArtistNotification($name),
            'hasBookmarked'  => $bookmark->isArtistBookmarked($artistId),
            'image'          => $this->artist->image(),
            'body'           => \Text::full_format($this->artist->body()),
            'bodyBbcode'     => $this->artist->body(),
            'vanityHouse'    => $this->artist->vanityHouse(),
            'tags'           => array_values($Tags),
            'similarArtists' => $JsonSimilar,
            'statistics' => [
                'numGroups'   => count($GroupIDs),
                'numTorrents' => $NumTorrents,
                'numSeeders'  => $NumSeeders,
                'numLeechers' => $NumLeechers,
                'numSnatches' => $NumSnatches,
                'numRequests' => count($JsonRequests),
            ],
            'torrentgroup' => $JsonTorrents,
            'requests'     => $JsonRequests,
        ];
    }

    protected function search_array($Array, $Key, $Value): array {
        $Results = [];
        if (is_array($Array)) {
            if (isset($Array[$Key]) && $Array[$Key] == $Value) {
                $Results[] = $Array;
            }
            foreach ($Array as $subarray) {
                $Results = array_merge($Results, $this->search_array($subarray, $Key, $Value));
            }
        }
        return $Results;
    }
}
