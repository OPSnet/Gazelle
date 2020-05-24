<?php

namespace Gazelle\Manager;

class Torrent {
    /** @var \DB_MYSQL */
    protected  $db;

    /** @var \CACHE */
    protected  $cache;

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Is this a valid torrenthash?
     * @param string $hash
     * @return string|bool The hash (with any spaces removed) if valid, otherwise false
     */
    public function isValidHash(string $hash) {
        //6C19FF4C 6C1DD265 3B25832C 0F6228B2 52D743D5
        $hash = str_replace(' ', '', $hash);
        return preg_match('/^[0-9a-fA-F]{40}$/', $hash) ? $hash : false;
    }

    /**
     * Map a torrenthash to a torrent id
     * @param string $hash
     * @return int The torrent id if found, otherwise null
     */
    public function hashToTorrentId(string $hash) {
        return $this->db->scalar("
            SELECT ID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a group id
     * @param string $hash
     * @return int The group id if found, otherwise null
     */
    public function hashToGroupId(string $hash) {
        return $this->db->scalar("
            SELECT GroupID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a torrent id and its group id
     * @param string $hash
     * @return array The torrent id and group id if found, otherwise null
     */
    public function hashToTorrentGroup(string $hash) {
        return $this->db->row("
            SELECT ID, GroupID
            FROM torrents
            WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrent id to a group id
     * @param int $torrentId
     * @return int The group id if found, otherwise null
     */
    public function idToGroupId(int $torrentId) {
        return $this->db->scalar("
            SELECT GroupID
            FROM torrents
            WHERE ID = ?
            ", $torrentId
        );
    }
}
