<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\Base {

    /** @see classes/config.php */
    protected $extendedSection = [
        1021 => 'Produced By',
        1022 => 'Composition',
        1023 => 'Remixed By',
        1024 => 'Guest Appearance',
    ];

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

    public function sectionName(int $sectionId): string {
        global $ReleaseTypes; /* FIXME */
        if (isset($this->extendedSection[$sectionId])) {
            return $this->extendedSection[$sectionId];
        } elseif (isset($ReleaseTypes[$sectionId])) {
            return $ReleaseTypes[$sectionId];
        } else {
            return "section:$sectionId";
        }
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        global $ReleaseTypes; /* FIXME */
        if (!isset($ReleaseTypes[$sectionId])) {
            return $this->sectionName($sectionId);
        }
        switch ($ReleaseTypes[$sectionId]) {
            case 'Anthology':
                return 'Anthologies';
            case 'DJ Mix':
                return 'DJ Mixes';
            case 'Remix':
                return 'Remixes';
            case 'Produced By':
            case 'Remixed By':
                return $this->sectionName($sectionId);
            default:
                return $this->sectionName($sectionId) . 's';
        }
    }
}
