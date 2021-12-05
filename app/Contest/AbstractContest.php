<?php

namespace Gazelle\Contest;

trait TorrentLeaderboard {
    public function leaderboard(int $limit, int $offset): array {
        $key = sprintf(\Gazelle\Contest::CONTEST_LEADERBOARD_CACHE_KEY,
            $this->id, (int)($offset/CONTEST_ENTRIES_PER_PAGE)
        );
        if (($leaderboard = self::$cache->get_value($key)) === false) {
            self::$db->prepared_query("
                SELECT
                    l.user_id,
                    um.Username as username,
                    l.entry_count,
                    l.last_entry_id,
                    t.Time as last_upload,
                    t.GroupID as group_id
                FROM contest_leaderboard l
                INNER JOIN torrents t ON (t.ID = l.last_entry_id)
                INNER JOIN users_main um ON (um.ID = l.user_id)
                WHERE l.contest_id = ?
                ORDER BY l.entry_count DESC, t.Time ASC, l.user_id ASC
                LIMIT ? OFFSET ?
                ", $this->id, $limit, $offset
            );
            $leaderboard = self::$db->to_array(false, MYSQLI_ASSOC, false);

            $torMan = new \Gazelle\Manager\Torrent;
            for ($i = 0, $leaderboardCount = count($leaderboard); $i < $leaderboardCount; $i++) {
                $torrent = $torMan->findById($leaderboard[$i]['last_entry_id']);
                $leaderboard[$i]['last_entry_link']
                    = $torrent->link() . ' [' . $torrent->label() . ']';
            }
            self::$cache->cache_value($key, $leaderboard, 3600);
        }
        return $leaderboard;
    }
}

abstract class AbstractContest extends \Gazelle\Base {
    protected $id;
    protected $begin;
    protected $end;

    public function __construct(int $id, string $begin, string $end) {
        $this->id    = $id;
        $this->begin = $begin;
        $this->end   = $end;
    }

    abstract public function ranker(): array;
    abstract public function participationStats(): array;
    abstract public function userPayout(float $enabledUserBonus, float $contestBonus, float $perEntryBonus): array;
}
