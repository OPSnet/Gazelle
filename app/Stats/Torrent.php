<?php

namespace Gazelle\Stats;

class Torrent extends \Gazelle\Base {

    protected $stats;

    const CACHE_KEY = 'stats_torrent';

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
        $stats = [
            'day'       => [],
            'week'      => [],
            'month'     => [],
            'quarter'   => [],
            'total-users' => \Users::get_enabled_users_count(),
        ];

        list($stats['torrent-count'], $stats['total-size'], $stats['total-files']) = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents
        ');

        list($stats['day']['count'], $stats['day']['size'], $stats['day']['files']) = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 1 DAY
        ');

        list($stats['week']['count'], $stats['week']['size'], $stats['week']['files']) = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 7 DAY
        ');

        list($stats['month']['count'], $stats['month']['size'], $stats['month']['files']) = $this->db->row('
            SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 30 DAY
        ');

        list($stats['quarter']['count'], $stats['quarter']['size'], $stats['quarter']['files']) = $this->db->row('
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
}

