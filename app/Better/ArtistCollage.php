<?php

namespace Gazelle\Better;

class ArtistCollage extends AbstractBetter {
    public function mode(): string {
        return 'artist';
    }

    public function heading(): string {
        return 'Artists in collages without images';
    }

    public function configure(): void {
        $this->distinct  = true;
        $this->field     = 'a.ArtistID';
        $this->baseQuery = "
            FROM artists_group a
            INNER JOIN collages_artists ca USING (ArtistID)
            INNER JOIN artists_alias    aa ON (a.PrimaryAlias = aa.AliasID)
            LEFT JOIN wiki_artists      wa USING (RevisionID)
            LEFT JOIN artist_usage      au ON (au.artist_id = a.ArtistID)";

        $this->where[] = "(wa.Image IS NULL OR wa.Image = '')";
        $this->orderBy = "ORDER BY coalesce(au.uses, 0) DESC, aa.Name ASC";
    }
}
