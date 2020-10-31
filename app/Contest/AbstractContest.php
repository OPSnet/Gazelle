<?php

namespace Gazelle\Contest;

trait TorrentLeaderboard {
    public function leaderboard(int $maxTracked): array {
        $key = sprintf(AbstractContest::LEADERBOARD_CACHE_KEY, $this->id);
        if (($leaderboard = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query("
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
                LIMIT ?
                ", $this->id, $maxTracked
            );
            $leaderboard = $this->db->to_array(false, MYSQLI_ASSOC);
            $oldLeaderboard = $this->cache->get_value('tmp_' . $key);
            if ($oldLeaderboard === false) {
                $oldLeaderboard = [];
            }
            $torMan = new \Gazelle\Manager\Torrent;
            $labelMan = new \Gazelle\Manager\TorrentLabel;
            $labelMan->showMedia(true)->showEdition(true)->showFlags(false);

            $leaderboardCount = count($leaderboard);
            $oldLeaderboardCount = count($oldLeaderboard);
            for ($i = 0; $i < $leaderboardCount; $i++) {
                for ($j = 0; $j < $oldLeaderboardCount; $j++) {
                    if ($leaderboard[$i]['user_id'] === $oldLeaderboard[$j]['user_id']) {
                        if ($leaderboard[$i]['last_entry_id'] === $oldLeaderboard[$j]['last_entry_id']) {
                            $leaderboard[$i]['last_entry_link'] = $oldLeaderboard[$j]['last_entry_link'];
                        }
                        break;
                    }
                }
                if (empty($leaderboard[$i]['last_entry_link'])) {
                    [$group, $torrent] = $torMan
                        ->setTorrentId($leaderboard[$i]['last_entry_id'])
                        ->setGroupId($leaderboard[$i]['group_id'])
                        ->torrentInfo();
                    $leaderboard[$i]['last_entry_link'] = sprintf(
                        '%s - <a href="torrents.php?id=%d&amp;torrentid=%d">%s</a> - %s',
                        $torMan->artistHtml(),
                        $group['ID'],
                        $leaderboard[$i]['last_entry_id'],
                        $group['Name'],
                        $labelMan->load($torrent)->label(),
                    );
                }
            }
            $this->cache->cache_value($key, $leaderboard, 60 * 20);
            $this->cache->delete_value('tmp_' . $key);
        }
        return $leaderboard;
    }
}

abstract class AbstractContest extends \Gazelle\Base {
    protected $id;
    protected $begin;
    protected $end;

    const LEADERBOARD_CACHE_KEY = 'contest_leaderboardv2_%d';
    const STATS_CACHE_KEY = 'contest_stats_%d';

    public function __construct(int $id, string $begin, string $end) {
        parent::__construct();
        $this->id    = $id;
        $this->begin = $begin;
        $this->end   = $end;
    }

    abstract public function ranker(): array;
    abstract public function participationStats(): array;
    abstract public function userPayout(float $enabledUserBonus, float $contestBonus, float $perEntryBonus): array;

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
        $n = $this->db->affected_rows();
        $this->db->commit();
        $leaderboard = $this->cache->get_value(sprintf(self::LEADERBOARD_CACHE_KEY, $this->id));
        if ($leaderboard !== false) {
            $this->cache->cache_value(
                sprintf('tmp_' . self::LEADERBOARD_CACHE_KEY, $this->id),
                $leaderboard,
                60 * 60 * 7
            );
        }
        $this->cache->deleteMulti([sprintf(self::STATS_CACHE_KEY, $this->id), sprintf(self::LEADERBOARD_CACHE_KEY, $this->id)]);
        return $n;
    }
}
