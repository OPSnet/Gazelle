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
        $this->field     = 'a."ArtistID"';
        $this->baseQuery = <<<END_SQL
            from relay.artists_group       a
            inner join relay.artists_alias aa on (a."PrimaryAlias" = aa."AliasID")
            left join relay.artist_usage   au on (au.artist_id = {$this->field})
            left join relay.artist_discogs dg on (dg.artist_id = {$this->field})
        END_SQL;
        $this->where[] = "dg.artist_id is null";
        $this->orderBy = 'order by coalesce(au.uses, 0) desc, aa."Name" asc';
    }
}
