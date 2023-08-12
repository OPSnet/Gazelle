<?php

namespace Gazelle\Better;

class Checksum extends AbstractBetter {
    public function mode(): string {
        return 'torrent';
    }

    public function heading(): string {
        return 'CD rips with imperfect log scores';
    }

    public function configure(): void {
        $this->field = 't.ID';
        $this->baseQuery = "
            FROM torrents t
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)";

        $this->where[] = "t.HasLogDB = '1' AND t.LogChecksum = '0'";
        $this->orderBy = "ORDER BY tls.Snatched DESC, t.created ASC";

        if ($this->filter === 'snatched') {
            $this->where[] = "EXISTS (
                SELECT 1
                FROM xbt_snatched xs
                WHERE xs.fid = t.ID
                    AND xs.uid = ?)";
            $this->args[] = $this->user->id();
        } elseif ($this->filter === 'uploaded') {
            $this->where[] = 't.UserID = ?';
            $this->args[]  = $this->user->id();
        }
    }
}
