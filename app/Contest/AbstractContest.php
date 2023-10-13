<?php

namespace Gazelle\Contest;

trait TorrentLeaderboard {
    public function leaderboard(int $limit, int $offset): array {
        $key = sprintf(\Gazelle\Contest::CONTEST_LEADERBOARD_CACHE_KEY,
            $this->id, (int)($offset / CONTEST_ENTRIES_PER_PAGE)
        );
        $leaderboard = self::$cache->get_value($key);
        if ($leaderboard === false) {
            self::$db->prepared_query("
                SELECT DISTINCT
                    l.user_id,
                    l.entry_count,
                    l.last_entry_id,
                    t.created as last_upload,
                    t.GroupID as group_id
                FROM contest_leaderboard l
                INNER JOIN torrents t ON (t.ID = l.last_entry_id)
                INNER JOIN users_main um ON (um.ID = l.user_id)
                INNER JOIN xbt_files_users xfu ON (xfu.fid = t.ID AND xfu.uid = t.UserID)
                WHERE um.Enabled = '1'
                    AND xfu.remaining = 0
                    AND  l.contest_id = ?
                ORDER BY l.entry_count DESC, t.created ASC, l.user_id ASC
                LIMIT ? OFFSET ?
                ", $this->id, $limit, $offset
            );
            $leaderboard = self::$db->to_array(false, MYSQLI_ASSOC, false);

            $torMan = new \Gazelle\Manager\Torrent;
            for ($i = 0, $leaderboardCount = count($leaderboard); $i < $leaderboardCount; $i++) {
                $torrent = $torMan->findById($leaderboard[$i]['last_entry_id']);
                $leaderboard[$i]['last_entry_link']
                    = $torrent->groupLink() . ' ' . $torrent->label();
            }
            self::$cache->cache_value($key, $leaderboard, 3600);
        }
        return $leaderboard;
    }
}

abstract class AbstractContest extends \Gazelle\Base {
    public function __construct(
        protected readonly int $id,
        protected string $begin,
        protected string $end,
    ) {}

    abstract public function ranker(): array;
    abstract public function participationStats(): array;
    abstract public function userPayout(int $enabledUserBonus, int $contestBonus, int $perEntryBonus): array;
}
