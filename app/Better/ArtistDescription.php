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
        $this->field     = 'a."ArtistID"';
        $this->baseQuery = <<<END_SQL
            from relay.artists_group       a
            inner join relay.artists_alias aa on (a."PrimaryAlias" = aa."AliasID")
            left join relay.wiki_artists   wa using ("RevisionID")
            left join relay.artist_usage   au on (au."artist_id" = {$this->field})
        END_SQL;
        $this->where[] = "coalesce(wa.\"Body\", '') = ''";
        $this->orderBy = 'order by coalesce(au.uses, 0) desc, aa."Name" asc';
    }
}
