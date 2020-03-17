<?php

namespace Gazelle;

class Artist {
    /** @var \DB_MYSQL */
    private $db;

    /** @var \CACHE */
    private $cache;

    const CACHE_ALIAS = 'artist_alias_%d_%s';

    public function __construct (\DB_MYSQL $db, \CACHE $cache, $id) {
        $this->db = $db;
        $this->cache = $cache;
        $this->id = $id;
    }

    public function get_alias($name) {
        $this->db->prepared_query('
            SELECT AliasID
            FROM artists_alias
            WHERE ArtistID = ?
                AND ArtistID != AliasID
                AND Name = ?',
            $this->id, $name);
        list($alias) = $this->db->next_record();
        return empty($alias) ? $this->id : $alias;
    }
}
