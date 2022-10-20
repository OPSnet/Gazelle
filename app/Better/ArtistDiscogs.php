<?php

namespace Gazelle\Better;

class ArtistDiscogs extends AbstractBetter {

    public function mode(): string {
        return 'artist';
    }

    public function heading(): string {
        return 'Artists without Discogs IDs';
    }

    public function configure(): void {
        $this->field     = 'a.ArtistID';
        $this->baseQuery = "
            FROM artists_group a
            LEFT JOIN artist_usage au ON (au.artist_id = a.ArtistID)
            LEFT JOIN artist_discogs dg ON (dg.artist_id = a.ArtistID)";

        $this->where[] = "dg.artist_id IS NULL";
        $this->orderBy = "ORDER BY coalesce(au.uses, 0) DESC, a.Name ASC";
    }
}
