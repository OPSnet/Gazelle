<?php

namespace Gazelle\Manager;

class Artist {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    public function __construct (\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function createArtist($name) {
        $this->db->prepared_query('
            INSERT INTO artists_group (Name)
            VALUES (?)
            ', $name
        );
        $artistId = $this->db->inserted_id();

        $this->db->prepared_query('
            INSERT INTO artists_alias (ArtistID, Name)
            VALUES (?, ?)
            ', $artistId, $name
        );
        $aliasId = $this->db->inserted_id();

        $this->cache->increment('stats_artist_count');

        return [$artistId, $aliasId];
    }
}
