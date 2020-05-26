<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\Base {

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
