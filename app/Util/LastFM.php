<?php

namespace Gazelle\Util;

class LastFM extends \Gazelle\Base {

    protected const LASTFM_API_URL = 'http://ws.audioscrobbler.com/2.0/?method=';

    public function artistEventList($ArtistID, $Artist, $Limit = 15) {
        $ArtistEvents = self::$cache->get_value("artist_events_$ArtistID");
        if ($ArtistEvents === false) {
            $ArtistEvents = $this->fetch("artist.getEvents", ["artist" => $Artist, "limit" => $Limit]);
            self::$cache->cache_value("artist_events_$ArtistID", $ArtistEvents, 432000);
        }
        return $ArtistEvents;
    }

    public function username(int $userId): ?string {
        return self::$db->scalar("
            SELECT username FROM lastfm_users WHERE ID = ?
            ", $userId
        );
    }

    public function userInfo(\Gazelle\User $user): ?array {
        $lastfmName = $this->username($user->id());
        if (is_null($lastfmName)) {
            return null;
        }
        $Response = self::$cache->get_value("lastfm_user_info_$lastfmName");
        if ($Response === false) {
            $Response = $this->fetch("user.getInfo", ["user" => $lastfmName]);
            if (isset($Response['info']) && isset($Response['info']['user'])) {
                $Response = $Response['info']['user'];
                if (!isset($Response['playlists'])) {
                    $Reponse['playlists'] = 0;
                }
            } else {
                $Response = null;
            }
            $Response['username'] = $lastfmName;
            self::$cache->cache_value("lastfm_user_info_$lastfmName", $Response, 86400);
        }
        return $Response;
    }

    public function compare(int $viewerId, $Username, $Limit = 15) {
        $viewername = $this->username($viewerId);
        if (is_null($viewername)) {
            return null;
        }
        if (strcasecmp($viewername, $Username)) {
            [$viewername, $Username] = [$Username, $viewername];
        }
        $Response = self::$cache->get_value("lastfm_compare_$viewername" . "_$Username");
        if ($Response === false) {
            $Response = json_encode($this->fetch("tasteometer.compare",
                ["type1" => "user", "type2" => "user", "value1" => $viewername, "value2" => $Username, "limit" => $Limit]));
            self::$cache->cache_value("lastfm_compare_$viewername" . "_$Username", $Response, 86400 * 7);
        }
        return $Response;
    }

    public function lastTrack($Username) {
        if (!LASTFM_API_KEY) {
            return '';
        }
        $Response = self::$cache->get_value("lastfm_last_played_track_$Username");
        if ($Response === false) {
            $Response = $this->fetch("user.getRecentTracks", ["user" => $Username, "limit" => 1]);
            // Take the single last played track out of the response.
            $Response = json_encode($Response['recenttracks']['track']);
            self::$cache->cache_value("lastfm_last_played_track_$Username", $Response, 7200);
        }
        return $Response;
    }

    public function topArtists($Username, $Limit = 15) {
        $Response = self::$cache->get_value("lastfm_top_artists_$Username");
        if ($Response === false) {
            sleep(1);
            $Response = json_encode($this->fetch("user.getTopArtists", ["user" => $Username, "limit" => $Limit]));
            self::$cache->cache_value("lastfm_top_artists_$Username", $Response, 86400);
        }
        return $Response;
    }

    public function topAlbums($Username, $Limit = 15) {
        $Response = self::$cache->get_value("lastfm_top_albums_$Username");
        if ($Response === false) {
            sleep(2);
            $Response = json_encode($this->fetch("user.getTopAlbums", ["user" => $Username, "limit" => $Limit]));
            self::$cache->cache_value("lastfm_top_albums_$Username", $Response, 86400);
        }
        return $Response;
    }

    public function topTracks($Username, $Limit = 15) {
        $Response = self::$cache->get_value("lastfm_top_tracks_$Username");
        if ($Response === false) {
            sleep(3);
            $Response = json_encode($this->fetch("user.getTopTracks", ["user" => $Username, "limit" => $Limit]));
            self::$cache->cache_value("lastfm_top_tracks_$Username", $Response, 86400);
        }
        return $Response;
    }

    public function weeklyArtists($Limit = 100) {
        $Response = self::$cache->get_value("lastfm_top_artists_$Limit");
        if ($Response === false) {
            $Response = json_encode($this->fetch("chart.getTopArtists", ["limit" => $Limit]));
            self::$cache->cache_value("lastfm_top_artists_$Limit", $Response, 86400);
        }
        return $Response;
    }

    public function hypedArtists($Limit = 100) {
        $Response = self::$cache->get_value("lastfm_hyped_artists_$Limit");
        if ($Response === false) {
            $Response = json_encode($this->fetch("chart.getHypedArtists", ["limit" => $Limit]));
            self::$cache->cache_value("lastfm_hyped_artists_$Limit", $Response, 86400);
        }
        return $Response;
    }

    public function clear($viewerId, $Username, $UserID) {
        $Response = self::$cache->get_value("lastfm_clear_cache_$UserID");
        if ($Response === false) {
            // Prevent clearing the cache on the same uid page for the next 10 minutes.
            self::$cache->cache_value("lastfm_clear_cache_$UserID", 1, 600);
            self::$cache->delete_value("lastfm_user_info_$Username");
            self::$cache->delete_value("lastfm_last_played_track_$Username");
            self::$cache->delete_value("lastfm_top_artists_$Username");
            self::$cache->delete_value("lastfm_top_albums_$Username");
            self::$cache->delete_value("lastfm_top_tracks_$Username");
            $viewername = $this->username($viewerId);
            if (!is_null($viewername)) {
                if (strcasecmp($viewername, $Username)) {
                    [$viewername, $Username] = [$Username, $viewername];
                }
                self::$cache->delete_value("lastfm_compare_{$Username}_$Username");
            }
        }
    }

    protected function fetch(string $Method, array $Args) {
        if (!LASTFM_API_KEY) {
            return false;
        }
        $RecentFailsKey = 'lastfm_api_fails';
        $RecentFails = (int)self::$cache->get_value($RecentFailsKey);
        if ($RecentFails > 5) {
            // Take a break if last.fm's API is down/nonfunctional
            return false;
        }
        $Url = self::LASTFM_API_URL . $Method;
        if (is_array($Args)) {
            foreach ($Args as $Key => $Value) {
                $Url .= "&$Key=" . urlencode($Value);
            }
            $Url .= "&format=json&api_key=" . LASTFM_API_KEY;
            $curl = new Curl;
            if ($curl->fetch($Url)) {
                return json_decode($curl->result(), true);
            } else {
                self::$cache->cache_value($RecentFailsKey, $RecentFails + 1, 1800);
                return false;
            }
        }
    }
}
