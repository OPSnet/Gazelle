<?php

namespace Gazelle\Better;

class ArtistDescription extends AbstractBetter {

    public function mode(): string {
        return 'artist';
    }

    public function heading(): string {
        return 'Artists without descriptions/biographies';
    }

    public function configure(): void {
        $this->field     = 'a.ArtistID';
        $this->baseQuery = "
            FROM artists_group a
            LEFT JOIN wiki_artists wa USING (RevisionID)
            LEFT JOIN artist_usage au ON (au.artist_id = a.ArtistID)";

        $this->where[] = "(wa.Body IS NULL OR wa.Body = '')";
        $this->orderBy = "ORDER BY coalesce(au.uses, 0) DESC, a.Name ASC";
    }
}
