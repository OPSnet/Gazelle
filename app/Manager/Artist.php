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

    public function sectionName(int $sectionId): ?string {
        return (new \Gazelle\ReleaseType)->findExtendedNameById($sectionId);
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        return (new \Gazelle\ReleaseType)->sectionTitle($sectionId);
    }
}
