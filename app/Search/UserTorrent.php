<?php

namespace Gazelle\Search;

use Gazelle\Enum\UserTorrentSearch;
use Gazelle\Manager\Torrent as TorrentManager;
use Gazelle\Search\UserTorrent\AbstractSearch;
use Gazelle\Torrent;
use Gazelle\User;

/**
 * Collect the IDs of torrents that a user has uploaded, snatched
 * or is seeding. This is used for creating ZIP downloads of the
 * corresponding .torrent files.
 */

class UserTorrent extends \Gazelle\Base {
    protected AbstractSearch $searcher;

    public function __construct(
        protected User                    $user,
        protected User                    $viewer,
        public readonly UserTorrentSearch $type,
        public readonly int               $limit,
        public readonly int               $offset,
    ) {
        $this->searcher = $type->searcher($this->user, $this->viewer);
    }

    public function total(): int {
        return $this->pg()->scalarex(<<<END_SQL
            select count(distinct t."ID")
            from relay.torrents t {$this->searcher->join()} where {$this->searcher->where()}
            END_SQL,
                ...$this->searcher->args()
        );
    }

    /**
     * Return the list of torrent ids
     * If $limit and $offset are both set to zero, the entire resultset is returned.
     * This is a bit of an awful hack, but it is required to handle the case of
     * collecting your own unseeded uploads for example.
     *
     * @return array<int>
     */
    public function idList(): array {
        $sql  = "select distinct t.\"ID\", {$this->searcher->orderColumn()} as seq from relay.torrents t {$this->searcher->join()} where {$this->searcher->where()} order by seq desc";
        $args = $this->searcher->args();
        if ($this->limit !== 0 || $this->offset !== 0) {
            $sql .= " limit {$this->pg()->placeholder(count($args))} offset {$this->pg()->placeholder(count($args) + 1)}";
            $args = array_merge($args, [$this->limit, $this->offset]);
        }
        return $this->pg()->columnex($sql, ...$args);
    }

    /**
     * @return array<Torrent>
     */
    public function torrentList(TorrentManager $manager = new TorrentManager()): array {
        return array_filter(
            array_map($manager->findById(...), $this->idList()),
            fn ($t) => $t instanceof Torrent
        );
    }
}
