<?php

namespace Gazelle\Better;

use Gazelle\Enum\TorrentFlag;

class Bad extends AbstractBetter {
    protected TorrentFlag $torrentFlag;

    public function mode(): string {
        return 'torrent';
    }

    public function setBadType(string $bad): static {
        $this->torrentFlag = match ($bad) { /** @phpstan-ignore-line */
            'files'   => TorrentFlag::badFile,
            'folders' => TorrentFlag::badFolder,
            'lineage' => TorrentFlag::noLineage,
            'tags'    => TorrentFlag::badTag,
        };
        return $this;
    }

    public function torrentFlag(): TorrentFlag {
        return $this->torrentFlag;
    }

    public function heading(): string {
        return match ($this->torrentFlag) { /** @phpstan-ignore-line */
            TorrentFlag::badFile   => 'Releases with with bad filenames',
            TorrentFlag::badFolder => 'Releases with with bad folders',
            TorrentFlag::noLineage => 'Releases with missing lineage details',
            TorrentFlag::badTag    => 'Releases with with bad tags',
        };
    }

    public function configure(): void {
        $this->field     = 't.ID';
        $this->baseQuery = "
            FROM torrents t
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            INNER JOIN {$this->torrentFlag->value} bad ON (bad.TorrentID = t.ID)
            ";
        $this->orderBy = "ORDER BY bad.TimeAdded ASC";

        if ($this->filter === 'snatched') {
            $this->where[] = "EXISTS (
                SELECT 1
                FROM xbt_snatched xs
                WHERE xs.fid = t.ID
                    AND xs.uid = ?)";
            $this->args[]     = $this->user->id();
        } elseif ($this->filter === 'uploaded') {
            $this->where[]    = 't.UserID = ?';
            $this->args[]     = $this->user->id();
        }
    }
}
