<?php

namespace Gazelle\Better;

use Gazelle\Enum\BetterFilter;
use Gazelle\Enum\TorrentFlag;

class Bad extends AbstractBetter {
    protected TorrentFlag $torrentFlag;

    public function mode(): string {
        return 'torrent';
    }

    public function setBadType(TorrentFlag $flag): static {
        $this->torrentFlag = $flag;
        return $this;
    }

    public function torrentFlag(): TorrentFlag {
        return $this->torrentFlag;
    }

    public function heading(): string {
        return match ($this->torrentFlag) { /** @phpstan-ignore-line */
            TorrentFlag::badFile   => 'Releases with bad filenames',
            TorrentFlag::badFolder => 'Releases with bad folders',
            TorrentFlag::noLineage => 'Releases with missing lineage details',
            TorrentFlag::badTag    => 'Releases with bad tags',
            TorrentFlag::trumpable => 'Releases marked as trumpable',
        };
    }

    public function configure(): void {
        $this->field     = 't."ID"';
        $this->baseQuery = <<<END_SQL
            from relay.torrents               t
            inner join relay.torrents_group   tg   on (tg."ID" = t."GroupID")
            inner join relay.torrent_has_attr bad  on (bad."TorrentID" = t."ID")
            inner join relay.torrent_attr     attr on (attr."ID" = bad."TorrentAttrID")
        END_SQL;
        $this->orderBy = 'order by bad.created';
        $this->where[] = "attr.\"Name\" = {$this->pg()->placeholder(count($this->args))}";
        $this->args[] = $this->torrentFlag->value;

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
