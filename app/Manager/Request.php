<?php

namespace Gazelle\Manager;

class Request extends \Gazelle\BaseManager {

    protected const ID_KEY = 'zz_r_%d';

    public function findById(int $requestId): ?\Gazelle\Request {
        $key = sprintf(self::ID_KEY, $requestId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM requests WHERE ID = ?
                ", $requestId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Request($id) : null;
    }

    /**
     * Find a list of unfilled requests by a user, sorted
     * by most number of votes and then largest bounty
     *
     * @return array of \Gazelle\Request objects
     */
    public function findUnfilledByUser(\Gazelle\User $user, int $limit): array {
        self::$db->prepared_query("
            SELECT r.ID
            FROM requests r
            INNER JOIN requests_votes v ON (v.RequestID = r.ID)
            WHERE r.TorrentID = 0
                AND r.UserID = ?
            GROUP BY r.ID
            ORDER BY count(v.UserID) DESC, sum(v.Bounty) DESC
            LIMIT 0, ?
            ", $user->id(), $limit
        );
        return array_map(fn($id) => $this->findById($id), self::$db->collect(0, false));
    }

    public function findByArtist(\Gazelle\Artist $artist): array {
        $artistId = $artist->id();
        $key = sprintf(\Gazelle\Artist::CACHE_REQUEST_ARTIST, $artistId);
        $requestList = self::$cache->get_value($key);
        if ($requestList === false) {
            self::$db->prepared_query("
                SELECT r.ID
                FROM requests AS r
                INNER JOIN requests_votes v ON (v.RequestID = r.id)
                INNER JOIN requests_artists AS ra ON (ra.RequestID = r.ID)
                WHERE r.TorrentID = 0
                    AND ra.ArtistID = ?
                GROUP BY r.ID
                ORDER BY count(v.UserID) DESC, sum(v.Bounty) DESC
                ", $artistId
            );
            $requestList = self::$db->collect(0, false);
            self::$cache->cache_value($key, $requestList, 3600);
        }
        return array_map(fn($id) => $this->findById($id), $requestList);
    }
}
