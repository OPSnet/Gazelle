<?php

namespace Gazelle\Contest;

trait TorrentLeaderboard {
    public function leaderboard(int $maxTracked): array {
        $key = "contest_leaderboard_" . $this->id;
        if (($leaderboard = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query("
                SELECT
                    l.user_id,
                    l.entry_count,
                    l.last_entry_id,
                    t.Time as last_upload
                FROM contest_leaderboard l
                INNER JOIN torrents t ON (t.ID = l.last_entry_id)
                WHERE l.contest_id = ?
                ORDER BY l.entry_count DESC, t.Time ASC, l.user_id ASC
                LIMIT ?
                ", $this->id, $maxTracked
            );
            $leaderboard = $this->db->to_array(false, MYSQLI_ASSOC);
            $this->cache->cache_value($key, $leaderboard, 60 * 20);
        }
        return $leaderboard;
    }
}

abstract class AbstractContest extends \Gazelle\Base {
    protected $id;
    protected $begin;
    protected $end;
    protected $stats; /* entries, users */

    public function __construct(int $id, string $begin, string $end) {
        parent::__construct();
        $this->id    = $id;
        $this->begin = $begin;
        $this->end   = $end;
    }

    abstract public function ranker(): array;
    abstract public function participationStats(): array;
    abstract public function userPayout(float $enabledUserBonus, float $contestBonus, float $perEntryBonus): array;

    public function totalEntries(): int {
        if (!$this->stats) {
            $this->stats = $this->participationStats();
        }
        return $stats[0] ?? 0;
    }

    public function totalUsers(): int {
        if (!$this->stats) {
            $this->stats = $this->participationStats();
        }
        return $stats[1] ?? 0;
    }

    public function calculateLeaderboard(): int {
        /* only called from schedule, don't need to worry about caching this */
        [$subquery, $args] = $this->ranker();
        $this->db->begin_transaction();
        $this->db->prepared_query('DELETE FROM contest_leaderboard WHERE contest_id = ?', $this->id);
        $this->db->prepared_query("
            INSERT INTO contest_leaderboard
                (contest_id, user_id, entry_count, last_entry_id)
            SELECT ?, LADDER.userid, LADDER.nr, T.ID
            FROM torrents_group TG
            LEFT JOIN torrents_artists TA ON (TA.GroupID = TG.ID)
            LEFT JOIN artists_group AG ON (AG.ArtistID = TA.ArtistID)
            INNER JOIN torrents T ON (T.GroupID = TG.ID)
            INNER JOIN (
                $subquery
            ) LADDER on (LADDER.last_torrent = T.ID)
            GROUP BY
                LADDER.nr,
                T.ID,
                TG.Name,
                T.Time
            ", $this->id, ...$args
        );
        $this->db->commit();
        $total = $this->totalEntries();
        $this->cache->delete_value("contest_leaderboard_" . $this->id);
        $this->cache->cache_value("contest_leaderboard_total_" . $this->id, $count, 3600 * 6);
        return $total;
    }
}
