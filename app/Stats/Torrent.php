<?php

namespace Gazelle\Stats;

class Torrent extends \Gazelle\Base {

    protected $stats;
    protected $peerStats;
    protected $busy = false;

    const CACHE_KEY = 'stats_torrent';
    const PEER_KEY  = 'stats_peers';
    const CALC_STATS_LOCK = 'stats_peers_lock';

    public function __construct() {
        $stats = self::$cache->get_value(self::CACHE_KEY);
        if ($this->stats === false) {
            $this->stats = $this->init();
        } else {
            $this->stats = $stats;
        }
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

    /* Get the yearly torrent flows (added, removed and net per month)
     * @return array
     *      - array keyed by month with [net, add, del]
     *      - array keyed by month with [category-id]
     */
    public function yearlyFlow(): array {
        if (!([$flow, $torrentCat] = self::$cache->get_value('torrentflow'))) {
            self::$db->prepared_query("
                SELECT date_format(t.Time,'%Y-%m') AS Month,
                    count(*) as t_net
                FROM torrents t
                GROUP BY Month
                ORDER BY Time DESC
                LIMIT 12
            ");
            $net = self::$db->to_array('Month', MYSQLI_ASSOC, false);

            self::$db->prepared_query("
                SELECT date_format(Time,'%Y-%m') as Month,
                    sum(Message LIKE 'Torrent % was uploaded by %') AS t_add,
                    sum(Message LIKE 'Torrent % was deleted %')     AS t_del
                FROM log
                GROUP BY Month order by Time DESC
                LIMIT 12
            ");
            $flow = array_merge_recursive($net, self::$db->to_array('Month', MYSQLI_ASSOC, false));
            asort($flow);
            $flow = array_slice($flow, -12);

            self::$db->prepared_query("
                SELECT tg.CategoryID, count(*)
                FROM torrents AS t
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.CategoryID
                ORDER BY 2 DESC
            ");
            $torrentCat = self::$db->to_array();
            self::$cache->cache_value('torrentflow', [$flow, $torrentCat], mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for dec -> jan
        }
        return [$flow, $torrentCat];
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

    public function leecherCount() {
        return $this->busy ? 'Server busy' : $this->peerStats()['leecher_count'];
    }

    public function seederCount() {
        return $this->busy ? 'Server busy' : $this->peerStats()['seeder_count'];
    }

    public function peerCount() {
        return $this->busy ? 'Server busy' : $this->peerStats()['leecher_count'] + $this->peerStats()['seeder_count'];
    }

    protected function peerStats(): array {
        if ($this->peerStats) {
            return $this->peerStats;
        }
        $this->peerStats = self::$cache->get_value(self::PEER_KEY);
        if ($this->peerStats !== false && isset($this->peerStats['leecher_count'])) {
            return $this->peerStats;
        }
        $this->busy = (bool)self::$cache->get_value(self::CALC_STATS_LOCK);
        if ($this->busy) {
            return [];
        }
        self::$cache->cache_value(self::CALC_STATS_LOCK, 1, 30);
        self::$db->prepared_query("
            SELECT if(remaining = 0, 'Seeding', 'Leeching') AS Type,
                count(uid)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type
        ");
        $stats = self::$db->to_array(0, MYSQLI_NUM, false);
        if (count($stats)) {
            $this->peerStats = [
                'leecher_count' => $stats['Leeching'][1],
                'seeder_count'  => $stats['Seeding'][1],
            ];
        } else {
            $this->peerStats = [
                'leecher_count' => 0,
                'seeder_count'  => 0,
            ];
        }
        self::$cache->cache_value(self::PEER_KEY, $this->peerStats, 86400 * 2);
        self::$cache->delete_value(self::CALC_STATS_LOCK);
        $this->busy = false;
        return $this->peerStats;
    }
}
