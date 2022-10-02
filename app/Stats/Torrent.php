<?php

namespace Gazelle\Stats;

class Torrent extends \Gazelle\Base {

    protected array $stats;
    protected array $peerStats;

    const CACHE_KEY      = 'stats_global_torrent';
    const PEER_KEY       = 'stats_global_peer';
    const TORRENT_FLOW   = 'stats_tflow';
    const CATEGORY_TOTAL = 'stats_tcat';

    public function __construct() {
        $this->stats = self::$cache->get_value(self::CACHE_KEY) ?: $this->init();
    }

    public function torrentCount() { return $this->stats['torrent-count']; }

    public function totalFiles() { return $this->stats['total-files']; }
    public function totalSize() { return $this->stats['total-size']; }
    public function totalUsers() { return $this->stats['total-users']; }

    public function amount(string $interval) { return $this->stats[$interval]['count']; }
    public function files(string $interval) { return $this->stats[$interval]['files']; }
    public function size(string $interval) { return $this->stats[$interval]['size']; }

    public function category() { return $this->stats['category']; }
    public function format() { return $this->stats['format']; }
    public function formatMonth() { return $this->stats['format-month']; }
    public function media() { return $this->stats['media']; }

    protected function init(): array {
        $userMan = new \Gazelle\Manager\User;
        $stats = [
            'day'       => [],
            'week'      => [],
            'month'     => [],
            'quarter'   => [],
            'total-users' => $userMan->getEnabledUsersCount(),
        ];

        [$stats['torrent-count'], $stats['total-size'], $stats['total-files']] = self::$db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents
        ');

        [$stats['day']['count'], $stats['day']['size'], $stats['day']['files']] = self::$db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 1 DAY
        ');

        [$stats['week']['count'], $stats['week']['size'], $stats['week']['files']] = self::$db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 7 DAY
        ');

        [$stats['month']['count'], $stats['month']['size'], $stats['month']['files']] = self::$db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 30 DAY
        ');

        [$stats['quarter']['count'], $stats['quarter']['size'], $stats['quarter']['files']] = self::$db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 120 DAY
        ');

        self::$db->prepared_query('
            SELECT Format, Encoding, count(*) as n
            FROM torrents
            GROUP BY Format, Encoding WITH ROLLUP
        ');
        $stats['format'] = self::$db->to_array(false, MYSQLI_NUM);

        self::$db->prepared_query('
            SELECT Format, Encoding, count(*) as n
            FROM torrents
            WHERE Time > now() - INTERVAL 1 MONTH
            GROUP BY Format, Encoding WITH ROLLUP
        ');
        $stats['format-month'] = self::$db->to_array(false, MYSQLI_NUM);

        self::$db->prepared_query('
            SELECT t.Media, count(*) as n
            FROM torrents t
            GROUP BY t.Media WITH ROLLUP
        ');
        $stats['media'] = self::$db->to_array(false, MYSQLI_NUM);

        self::$db->prepared_query('
            SELECT tg.CategoryID, count(*) AS n
            FROM torrents_group tg
            WHERE EXISTS (
                SELECT 1 FROM torrents t WHERE t.GroupID = tg.ID)
            GROUP BY tg.CategoryID
            ORDER BY 2 DESC
        ');
        $stats['category'] = self::$db->to_array(false, MYSQLI_NUM);

        self::$cache->cache_value(self::CACHE_KEY, $this->stats = $stats, 7200);
        return $stats;
    }

    /**
     * Yearly torrent flows (added, removed and net per month)
     */
    public function flow(): array {
        $flow = self::$cache->get_value(self::TORRENT_FLOW);
        if ($flow === false) {
            self::$db->prepared_query("
                SELECT date_format(t.Time,'%Y-%m') AS Month,
                    count(*) as t_net
                FROM torrents t
                GROUP BY Month
                ORDER BY Time DESC
                LIMIT 12
            ");
            $flow = self::$db->to_array('Month', MYSQLI_ASSOC, false);
            $beginMonth = end($flow)['Month'] . '-01';
            $endMonth = reset($flow)['Month'] . '-01';

            self::$db->prepared_query("
                SELECT date_format(Time,'%Y-%m') as Month,
                    sum(Message LIKE 'Torrent % was uploaded by %') AS t_add,
                    sum(Message LIKE 'Torrent % was deleted %')     AS t_del
                FROM log
                WHERE Time BETWEEN ? AND last_day(?)
                GROUP BY Month order by Time DESC
                ", $beginMonth, $endMonth
            );
            $delta = self::$db->to_array('Month', MYSQLI_ASSOC, false);
            foreach (array_keys($flow) as $m) {
                $flow[$m]['t_add'] = (int)($delta[$m]['t_add'] ?? 0);
                $flow[$m]['t_del'] = -(int)($delta[$m]['t_del'] ?? 0);
            }
            $flow = array_reverse($flow);
            self::$cache->cache_value(self::TORRENT_FLOW, $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for dec -> jan
        }
        return $flow;
    }

    /**
     * Get the totals by category
     */
    public function categoryTotal(): array {
        $total = self::$cache->get_value(self::CATEGORY_TOTAL);
        $total = false;
        if ($total === false) {
            self::$db->prepared_query("
                SELECT tg.CategoryID,
                    count(*) as total
                FROM torrents AS t
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.CategoryID
                ORDER BY 2 DESC
            ");
            $total = [];
            foreach (self::$db->to_pair('CategoryID', 'total', false) as $cat => $value) {
                $total[] = [
                    'name' => CATEGORY[$cat - 1],
                    'y'    => $value,
                ];
            }
            self::$cache->cache_value(self::CATEGORY_TOTAL, $total, mktime(0, 0, 0, date('n') + 1, 2));
        }
        return $total;
    }

    /**
     * Get the number of albums (category 1 == Music)
     * @return int count
     */
    public function albumCount(): int {
        if (($count = self::$cache->get_value('stats_album_count')) === false) {
            $count = self::$db->scalar("
                SELECT count(*) FROM torrents_group WHERE CategoryID = 1
            ");
            self::$cache->cache_value('stats_album_count', $count, 7200 + rand(0, 300));
        }
        return $count;
    }

    /**
     * Get the number of artists
     * @return int count
     */
    public function artistCount(): int {
        if (($count = self::$cache->get_value('stats_artist_count')) === false) {
            $count = self::$db->scalar("
                SELECT count(*) FROM artists_group
            ");
            self::$cache->cache_value('stats_artist_count', $count, 7200 + rand(0, 300));
        }
        return $count;
    }

    /**
     * Get the number of perfect flacs
     * @return int count
     */
    public function perfectCount(): int {
        if (($count = self::$cache->get_value('stats_perfect_count')) === false) {
            $count = self::$db->scalar("
                SELECT count(*)
                FROM torrents
                WHERE Format = 'FLAC'
                    AND (
                        (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                        OR
                        (Media in ('BD', 'DVD', 'Soundboard', 'WEB', 'Vinyl'))
                    )
            ");
            self::$cache->cache_value('stats_perfect_count', $count, 7200 + rand(0, 300));
        }
        return $count;
    }

    public function leecherCount(): int {
        return $this->peerStats()['leech_total'];
    }

    public function seederCount(): int {
        return $this->peerStats()['seeding_total'];
    }

    public function peerCount(): int {
        return $this->leecherCount() + $this->seederCount();
    }

    public function peerStats(): array {
        if (!empty($this->peerStats)) {
            return $this->peerStats;
        }
        $stats = self::$cache->get_value(self::PEER_KEY);
        if ($stats === false) {
            $stats = self::$db->rowAssoc("
                SELECT sum(leech_total) AS leech_total,
                    sum(seeding_total) AS seeding_total
                FROM user_summary
            ");
            if ($stats) {
                $stats = array_map(fn($n) => (int)$n, $stats);
            } else {
                $stats = ['leech_total' => 0, 'seeding_total' => 0];
            }
            self::$cache->cache_value(self::PEER_KEY, $stats, 3600);
        }
        $this->peerStats = $stats;
        return $this->peerStats;
    }
}
