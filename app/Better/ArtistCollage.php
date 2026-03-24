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
        $this->field     = 'a."ArtistID"';
        $this->baseQuery = <<<END_SQL
            from relay.artists_group          a
            inner join relay.collages_artists ca using ("ArtistID")
            inner join relay.artists_alias    aa on (a."PrimaryAlias" = aa."AliasID")
            left join relay.wiki_artists      wa using ("RevisionID")
            left join relay.artist_usage      au on (au.artist_id = {$this->field})
        END_SQL;
        $this->where[]  = "coalesce(wa.\"Image\", '') = ''";
        $this->orderBy  = 'order by coalesce(au.uses, 0) desc, aa."Name" asc';
        $this->distinct = 'coalesce(au.uses, 0), aa."Name"';
    }
}
