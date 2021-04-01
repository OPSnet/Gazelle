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
        parent::__construct();
        if (($this->stats = $this->cache->get_value(self::CACHE_KEY)) === false) {
            $this->init();
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

    protected function init() {
        $userMan = new \Gazelle\Manager\User;
        $stats = [
            'day'       => [],
            'week'      => [],
            'month'     => [],
            'quarter'   => [],
            'total-users' => $userMan->getEnabledUsersCount(),
        ];

        [$stats['torrent-count'], $stats['total-size'], $stats['total-files']] = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents
        ');

        [$stats['day']['count'], $stats['day']['size'], $stats['day']['files']] = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 1 DAY
        ');

        [$stats['week']['count'], $stats['week']['size'], $stats['week']['files']] = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 7 DAY
        ');

        [$stats['month']['count'], $stats['month']['size'], $stats['month']['files']] = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 30 DAY
        ');

        [$stats['quarter']['count'], $stats['quarter']['size'], $stats['quarter']['files']] = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 120 DAY
        ');

        $this->db->prepared_query('
            SELECT Format, Encoding, count(*) as n
            FROM torrents
            GROUP BY Format, Encoding WITH ROLLUP
        ');
        $stats['format'] = $this->db->to_array(false, MYSQLI_NUM);

        $this->db->prepared_query('
            SELECT Format, Encoding, count(*) as n
            FROM torrents
            WHERE Time > now() - INTERVAL 1 MONTH
            GROUP BY Format, Encoding WITH ROLLUP
        ');
        $stats['format-month'] = $this->db->to_array(false, MYSQLI_NUM);

        $this->db->prepared_query('
            SELECT t.Media, count(*) as n
            FROM torrents t
            GROUP BY t.Media WITH ROLLUP
        ');
        $stats['media'] = $this->db->to_array(false, MYSQLI_NUM);

        $this->db->prepared_query('
            SELECT tg.CategoryID, count(*) AS n
            FROM torrents_group tg
            WHERE EXISTS (
                SELECT 1 FROM torrents t WHERE t.GroupID = tg.ID)
            GROUP BY tg.CategoryID
            ORDER BY 2 DESC
        ');
        $stats['category'] = $this->db->to_array(false, MYSQLI_NUM);

        $this->cache->cache_value(self::CACHE_KEY, $this->stats = $stats, 7200);
    }

    /* Get the yearly torrent flows (added, removed and net per month)
     * @return array
     *      - array keyed by month with [net, add, del]
     *      - array keyed by month with [category-id]
     */
    public function yearlyFlow(): array {
        if (!([$flow, $torrentCat] = $this->cache->get_value('torrentflow'))) {
            $this->db->prepared_query("
                SELECT date_format(t.Time,'%Y-%m') AS Month,
                    count(*) as t_net
                FROM torrents t
                GROUP BY Month
                ORDER BY Time DESC
                LIMIT 12
            ");
            $net = $this->db->to_array('Month', MYSQLI_ASSOC, false);

            $this->db->prepared_query("
                SELECT date_format(Time,'%Y-%m') as Month,
                    sum(Message LIKE 'Torrent % was uploaded by %') AS t_add,
                    sum(Message LIKE 'Torrent % was deleted %')     AS t_del
                FROM log
                GROUP BY Month order by Time DESC
                LIMIT 12
            ");
            $flow = array_merge_recursive($net, $this->db->to_array('Month', MYSQLI_ASSOC, false));
            asort($flow);
            $flow = array_slice($flow, -12);

            $this->db->prepared_query("
                SELECT tg.CategoryID, count(*)
                FROM torrents AS t
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.CategoryID
                ORDER BY 2 DESC
            ");
            $torrentCat = $this->db->to_array();
            $this->cache->cache_value('torrentflow', [$flow, $torrentCat], mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for dec -> jan
        }
        return [$flow, $torrentCat];
    }

    /**
     * Get the number of albums (category 1 == Music)
     * @return int count
     */
    public function albumCount(): int {
        if (($count = $this->cache->get_value('stats_album_count')) === false) {
            $count = $this->db->scalar("
                SELECT count(*) FROM torrents_group WHERE CategoryID = 1
            ");
            $this->cache->cache_value('stats_album_count', $count, 7200 + rand(0, 300));
        }
        return $count;
    }

    /**
     * Get the number of artists
     * @return int count
     */
    public function artistCount(): int {
        if (($count = $this->cache->get_value('stats_artist_count')) === false) {
            $count = $this->db->scalar("
                SELECT count(*) FROM artists_group
            ");
            $this->cache->cache_value('stats_artist_count', $count, 7200 + rand(0, 300));
        }
        return $count;
    }

    /**
     * Get the number of perfect flacs
     * @return int count
     */
    public function perfectCount(): int {
        if (($count = $this->cache->get_value('stats_perfect_count')) === false) {
            $count = $this->db->scalar("
                SELECT count(*)
                FROM torrents
                WHERE Format = 'FLAC'
                    AND (
                        (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                        OR
                        (Media in ('BD', 'DVD', 'Soundboard', 'WEB', 'Vinyl'))
                    )
            ");
            $this->cache->cache_value('stats_perfect_count', $count, 7200 + rand(0, 300));
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
        $this->peerStats = $this->cache->get_value(self::PEER_KEY);
        if ($this->peerStats !== false && isset($this->peerStats['leecher_count'])) {
            return $this->peerStats;
        }
        $this->busy = (bool)$this->cache->get_value(self::CALC_STATS_LOCK);
        if ($this->busy) {
            return [];
        }
        $this->cache->cache_value(self::CALC_STATS_LOCK, 1, 30);
        $this->db->prepared_query("
            SELECT if(remaining = 0, 'Seeding', 'Leeching') AS Type,
                count(uid)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type
        ");
        $stats = $this->db->to_array(0, MYSQLI_NUM, false);
        $this->peerStats = [
            'leecher_count' => $stats['Leeching'][1] ?: 0,
            'seeder_count'  => $stats['Seeding'][1] ?: 0,
        ];
        $this->cache->cache_value(self::PEER_KEY, $this->peerStats, 86400 * 2);
        $this->cache->delete_value(self::CALC_STATS_LOCK);
        $this->busy = false;
        return $this->peerStats;
    }
}
