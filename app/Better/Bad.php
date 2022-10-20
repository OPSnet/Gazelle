<?php

namespace Gazelle\Better;

class Bad extends AbstractBetter {

    protected string $tableName;

    public function mode(): string {
        return 'torrent';
    }

    public function setBadType(string $bad): Bad {
        $this->tableName = match($bad) {
            'files'   => 'torrents_bad_files',
            'folders' => 'torrents_bad_folders',
            'lineage' => 'torrents_missing_lineage',
            'tags'    => 'torrents_bad_tags',
        };
        return $this;
    }

    public function tableName(): string {
        return $this->tableName;
    }

    public function heading(): string {
        return match($this->tableName) {
            'torrents_bad_files'       => 'Releases with with bad filenames',
            'torrents_bad_folders'     => 'Releases with with bad folders',
            'torrents_missing_lineage' => 'Releases with missing lineage details',
            'torrents_bad_tags'        => 'Releases with with bad tags',
        };
    }

    public function configure(): void {
        $this->field     = 't.ID';
        $this->baseQuery = "
            FROM torrents t
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            INNER JOIN {$this->tableName} bad ON (bad.TorrentID = t.ID)
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
