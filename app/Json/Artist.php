<?php

namespace Gazelle\Json;

class Artist extends \Gazelle\Json {
    protected $artist;
    protected $user;
    protected $releasesOnly = false;

    public function setArtist(\Gazelle\Artist $artist) {
        $this->artist = $artist;
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
        if (count($GroupIDs) > 0) {
            $groupList = \Torrents::get_groups($GroupIDs, true, true);
        } else {
            $groupList = [];
        }

        $JsonTorrents = [];
        $Tags = [];
        $NumTorrents = $NumSeeders = $NumLeechers = $NumSnatches = 0;
        $bookmark = new \Gazelle\Bookmark($this->user);

        foreach ($GroupIDs as $GroupID) {
            if (!isset($groupList[$GroupID])) {
                continue;
            }
            $Group = $groupList[$GroupID];
            $Torrents = $Group['Torrents'] ?? [];
            $artists = $Group['Artists'];

            foreach ($artists as &$A) {
                $A['id'] = (int)$A['id'];
                $A['aliasid'] = (int)$A['aliasid'];
            }

            $ExtendedArtists = $Group['ExtendedArtists'];
            foreach ($ExtendedArtists as &$artistGroup) {
                if (is_null($artistGroup)) {
                    continue;
                }
                foreach ($artistGroup as &$A) {
                    $A['id'] = (int)$A['id'];
                    $A['aliasid'] = (int)$A['aliasid'];
                }
            }

            $Found = $this->search_array($artists, 'id', $artistId);
            if ($this->releasesOnly && empty($Found)) {
                continue;
            }

            $TagList = explode(' ', $Group['TagList']);
            foreach ($TagList as $Tag) {
                if (!isset($Tags[$Tag])) {
                    $Tags[$Tag] = ['name' => $Tag, 'count' => 1];
                } else {
                    $Tags[$Tag]['count']++;
                }
            }
            $InnerTorrents = [];
            foreach ($Torrents as $Torrent) {
                $NumTorrents++;
                $NumSeeders += $Torrent['Seeders'];
                $NumLeechers += $Torrent['Leechers'];
                $NumSnatches += $Torrent['Snatched'];

                $InnerTorrents[] = [
                    'id' => (int)$Torrent['ID'],
                    'groupId' => (int)$Torrent['GroupID'],
                    'media' => $Torrent['Media'],
                    'format' => $Torrent['Format'],
                    'encoding' => $Torrent['Encoding'],
                    'remasterYear' => (int)$Torrent['RemasterYear'],
                    'remastered' => $Torrent['Remastered'] == 1,
                    'remasterTitle' => $Torrent['RemasterTitle'],
                    'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
                    'scene' => $Torrent['Scene'] == 1,
                    'hasLog' => $Torrent['HasLog'] == 1,
                    'hasCue' => $Torrent['HasCue'] == 1,
                    'logScore' => (int)$Torrent['LogScore'],
                    'fileCount' => (int)$Torrent['FileCount'],
                    'freeTorrent' => $Torrent['FreeTorrent'] == 1,
                    'size' => (int)$Torrent['Size'],
                    'leechers' => (int)$Torrent['Leechers'],
                    'seeders' => (int)$Torrent['Seeders'],
                    'snatched' => (int)$Torrent['Snatched'],
                    'time' => $Torrent['Time'],
                    'hasFile' => (int)$Torrent['HasFile']
                ];
            }
            $JsonTorrents[] = [
                'groupId'              => $GroupID,
                'groupName'            => $Group['Name'],
                'groupYear'            => $Group['Year'],
                'groupRecordLabel'     => $Group['RecordLabel'],
                'groupCatalogueNumber' => $Group['CatalogueNumber'],
                'groupCategoryID'      => $Group['CategoryID'],
                'tags'                 => $TagList,
                'releaseType'          => (int)$Group['ReleaseType'],
                'wikiImage'            => $Group['WikiImage'],
                'groupVanityHouse'     => $Group['VanityHouse'] == 1,
                'hasBookmarked'        => $bookmark->isTorrentBookmarked($GroupID),
                'artists'              => $artists,
                'extendedArtists'      => $ExtendedArtists,
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
                'numGroups'   => count($groupList),
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

    protected function search_array($Array, $Key, $Value) {
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
