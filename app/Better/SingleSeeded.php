<?php

namespace Gazelle\Better;

class SingleSeeded extends AbstractBetter {
    protected int $uploaderId;

    public function mode(): string {
        return 'torrent';
    }

    public function heading(): string {
        return 'FLAC releases with a single seeder';
    }

    public function setUploader(\Gazelle\User $user): static {
        $this->uploaderId = $user->id;
        return $this;
    }

    public function configure(): void {
        $this->field = 't."ID"';
        $this->baseQuery = <<<END_SQL
            from relay.torrents                   t
            inner join relay.torrents_group       tg  on (tg."ID" = t."GroupID")
            inner join relay.torrents_leech_stats tls on (tls."TorrentID" = t."ID")
        END_SQL;
        $this->where[] = "t.\"Format\" = 'FLAC' and tls.\"Seeders\" = 1";
        if (isset($this->uploaderId)) {
            $this->where[] = "t.\"UserID\" = {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $this->uploaderId;
        }
        $this->orderBy = "order by t.\"LogScore\" desc, random()";
    }
}
