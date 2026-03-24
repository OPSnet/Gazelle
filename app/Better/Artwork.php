<?php

namespace Gazelle\Better;

use Gazelle\Enum\BetterFilter;

class Artwork extends AbstractBetter {
    public function mode(): string {
        return 'group';
    }

    public function heading(): string {
        return 'Releases without image artwork/cover art';
    }

    public function configure(): void {
        $this->field = 'tg."ID"';
        $this->baseQuery = <<<END_SQL
            from relay.torrents_group     tg
            left join relay.wiki_torrents wt using ("RevisionID")
            left join tgroup_has_attr     tgha on (
                tgha.id_tgroup = {$this->field}
                and tgha.id_tgroup_attr = (
                    select tga.id_tgroup_attr from tgroup_attr tga where tga.name = 'no-cover-art'
                )
            )
            END_SQL;
        $this->where[] = 'tg."CategoryID" = 1 and coalesce(wt."Image", tg."WikiImage") = \'\' and tgha.id_tgroup is null';
        $this->orderBy = 'order by tg."Name"';

        if ($this->filter === BetterFilter::snatched) {
            $this->where[] = <<<END_SQL
                exists (
                    select 1
                    from relay.xbt_snatched xs
                    inner join relay.torrents t on (t."ID" = xs.fid and xs.uid = $1)
                    where t."GroupID" = tg."ID"
                )
            END_SQL;
            $this->args[] = $this->user->id;
        } elseif ($this->filter === BetterFilter::uploaded) {
            $this->where[] = <<<END_SQL
                exists (
                    select 1
                    from relay.torrents t
                    where t."GroupID" = tg."ID"
                        and t."UserID" = $1
                )
            END_SQL;
            $this->args[] = $this->user->id;
        }
    }
}
