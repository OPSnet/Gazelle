<?php

namespace Gazelle\Better;

use Gazelle\Enum\BetterFilter;

class Checksum extends AbstractBetter {
    public function mode(): string {
        return 'torrent';
    }

    public function heading(): string {
        return 'CD rips with imperfect log scores';
    }

    public function configure(): void {
        $this->field = 't."ID"';
        $this->baseQuery = <<<END_SQL
            from relay.torrents                   t
            inner join relay.torrents_group       tg  on (tg."ID" = t."GroupID")
            inner join relay.torrents_leech_stats tls on (tls."TorrentID" = t."ID")
        END_SQL;
        $this->where[] = "t.\"HasLogDB\" = '1' AND t.\"LogChecksum\" = '0'";
        $this->orderBy = 'order by tls."Snatched" DESC, t.created';

        if ($this->filter === BetterFilter::snatched) {
            $this->where[] = <<<END_SQL
                exists (
                    select 1
                    from relay.xbt_snatched xs
                    where xs.fid = t."ID"
                        and xs.uid = {$this->pg()->placeholder(count($this->args))}
                    )
            END_SQL;
            $this->args[] = $this->user->id;
        } elseif ($this->filter === BetterFilter::uploaded) {
            $this->where[] = "t.\"UserID\" = {$this->pg()->placeholder(count($this->args))}";
            $this->args[]  = $this->user->id;
        }
    }
}
