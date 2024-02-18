<?php

namespace Gazelle\Util;

class LastFM extends \Gazelle\Base {
    protected const LASTFM_API_URL = 'https://ws.audioscrobbler.com/2.0/?method=';

    public function flush(string $username): bool {
        $key = "lastfm_clear_cache_$username";
        $allowed = self::$cache->get_value($key);
        if (!$allowed) {
            return false;
        }
        // Prevent clearing the cache on the same uid page for the next 10 minutes.
        self::$cache->cache_value($key, true, 600);
        self::$cache->delete_multi([
            "lastfm_user_info_$username",
            "lastfm_last_played_track_$username",
            "lastfm_top_albums_$username",
            "lastfm_top_artists_$username",
            "lastfm_top_tracks_$username",
        ]);
        return true;
    }

    public function modifyUsername(\Gazelle\User $user, string $username): int {
        $previous = $this->username($user);
        if (!$previous && $username !== "") {
            self::$db->prepared_query("
                INSERT INTO lastfm_users (Username, ID)
                VALUES (?, ?)
                ", $username, $user->id()
            );
        } elseif ($previous && $username !== "") {
            self::$db->prepared_query("
                UPDATE lastfm_users SET
                    Username = ?
                WHERE ID = ?
                ", $username, $user->id()
            );
        } elseif ($previous && $username === "") {
            self::$db->prepared_query("
                DELETE FROM lastfm_users WHERE ID = ?
                ", $user->id()
            );
        }
        $affected = self::$db->affected_rows();
        if ($affected) {
            if ($previous) {
                $this->flush($previous);
            }
            $this->flush($username);
        }
        return $affected;
    }

    public function username(\Gazelle\User $user): ?string {
        $username = self::$db->scalar("
            SELECT username FROM lastfm_users WHERE ID = ?
            ", $user->id()
        );
        return is_null($username) ? null : (string)$username;
    }

    public function userInfo(\Gazelle\User $user): ?array {
        $username = $this->username($user);
        if (is_null($username)) {
            return null;
        }
        $key = 'lastfm_user_info_' . urlencode($username);
        $response = self::$cache->get_value($key);
        if ($response === false) {
            $response = $this->fetch("user.getInfo", ["user" => $username]);
            $response = $response['user'] ?? null;
            self::$cache->cache_value($key, $response, 86400);
        }
        return $response;
    }

    public function lastTrack(string $username): ?array {
        $key = "lastfm_last_played_track_$username";
        $response = self::$cache->get_value($key);
        if ($response === false) {
            $response = $this->fetch("user.getRecentTracks", ["user" => $username, "limit" => 1]);
            if ($response === false) {
                $response = ['none' => true];
            } else {
                // Take the single last played track out of the response.
                $info = $response['recenttracks']['track'][0];
                $response = [
                    'album'  => $info['album']['#text'],
                    'artist' => $info['artist']['#text'],
                    'name'   => $info['name'],
                ];
            }
            self::$cache->cache_value($key, $response, 7200);
        }
        return $response;
    }

    public function topAlbums(string $username, int $limit = 15): array {
        $key = "lastfm_top_albums_$username";
        $response = self::$cache->get_value($key);
        if ($response === false) {
            sleep(1);
            $response = $this->fetch("user.getTopAlbums", ["user" => $username, "limit" => $limit]);
            if ($response === false) {
                $response = [];
            } else {
                $top = [];
                foreach ($response['topalbums']['album'] as $entry) {
                    $top[] = [
                        'artist'    => $entry['artist']['name'],
                        'name'      => $entry['name'],
                        'playcount' => (int)$entry['playcount'],
                        'url'       => $entry['url'],
                    ];
                }
                $response = $top;
            }
            self::$cache->cache_value($key, $response, 86400);
        }
        return $response;
    }

    public function topArtists(string $username, int $limit = 15): ?array {
        $key = "lastfm_top_artists_$username";
        $response = self::$cache->get_value($key);
        if ($response === false) {
            sleep(1);
            $response = $this->fetch("user.getTopArtists", ["user" => $username, "limit" => $limit]);
            if ($response === false) {
                $response = [];
            } else {
                $top = [];
                foreach ($response['topartists']['artist'] as $entry) {
                    $top[] = [
                        'name'      => $entry['name'],
                        'playcount' => (int)$entry['playcount'],
                        'url'       => $entry['url'],
                    ];
                }
                $response = $top;
            }
            self::$cache->cache_value($key, $response, 86400);
        }
        return $response;
    }

    public function topTracks(string $username, int $limit = 15): array {
        $key = "lastfm_top_tracks_$username";
        $response = self::$cache->get_value($key);
        if ($response === false) {
            sleep(1);
            $response = $this->fetch("user.getTopTracks", ["user" => $username, "limit" => $limit]);
            if ($response === false) {
                $response = [];
            } else {
                $top = [];
                foreach ($response['toptracks']['track'] as $entry) {
                    $top[] = [
                        'artist'    => $entry['artist']['name'],
                        'name'      => $entry['name'],
                        'playcount' => (int)$entry['playcount'],
                        'url'       => $entry['url'],
                    ];
                }
                $response = $top;
            }

            self::$cache->cache_value($key, $response, 86400);
        }
        return $response;
    }

    public function weeklyArtists(int $limit = 50): array {
        $key = "lastfm_top_artists_$limit";
        $response = self::$cache->get_value($key);
        if ($response === false) {
            $response = $this->fetch("chart.getTopArtists", ["limit" => $limit]);
            if ($response === false) {
                $response = [];
            } else {
                $top = [];
                foreach ($response['artists']['artist'] as $entry) {
                    $top[] = [
                        'name'      => $entry['name'],
                        'playcount' => (int)$entry['playcount'],
                        'listeners' => (int)$entry['listeners'],
                    ];
                }
                $response = $top;
            }
            self::$cache->cache_value($key, $response, 86400);
        }
        return $response;
    }

    protected function fetch(string $method, array $args): array|false {
        if (LASTFM_API_KEY == false) {
            return false;
        }
        $recentFailsKey = 'lastfm_api_fails';
        $recentFails = (int)self::$cache->get_value($recentFailsKey);
        if ($recentFails > 5) {
            // Take a break if last.fm's API is down/nonfunctional
            return false;
        }
        $url = self::LASTFM_API_URL . $method;
        foreach ($args as $Key => $Value) {
            $url .= "&$Key=" . urlencode($Value);
        }
        $curl = new Curl();
        if ($curl->fetch($url . "&format=json&api_key=" . LASTFM_API_KEY)) {
            return json_decode($curl->result(), true);
        }
        self::$cache->cache_value($recentFailsKey, $recentFails + 1, 1800);
        return false;
    }
}
