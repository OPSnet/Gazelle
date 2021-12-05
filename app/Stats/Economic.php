<?php

namespace Gazelle\Stats;

class Economic extends \Gazelle\Base {

    protected $stats;

    const CACHE_KEY = 'stats_economic';

    public function get($key) {
        return $this->stats[$key] ?? null;
    }

    public function dump() {
        foreach ($this->stats as $k => $v) {
            printf("%s\t%s\n", $k, $v);
        }
    }

    public function __construct() {
        if (($this->stats = self::$cache->get_value(self::CACHE_KEY)) === false) {
            list(
                $this->stats['totalUpload'],
                $this->stats['totalDownload'],
                $this->stats['totalEnabled'],
            ) = self::$db->row('
                SELECT sum(uls.Uploaded), sum(uls.Downloaded), count(*)
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                ', '1'
            );

            $this->stats['totalBounty'] = self::$db->scalar('
                SELECT SUM(Bounty)
                FROM requests_votes
            ');

            $this->stats['availableBounty'] = self::$db->scalar('
                SELECT SUM(rv.Bounty)
                FROM requests_votes AS rv
                INNER JOIN requests AS r ON (r.ID = rv.RequestID)
            ');

            list(
                $this->stats['totalLiveSnatches'],
                $this->stats['totalTorrents'],
            ) = self::$db->row('
                SELECT sum(tls.Snatched), count(*)
                FROM torrents_leech_stats tls
            ');

            $this->stats['totalOverallSnatches'] = self::$db->scalar('
                SELECT count(*)
                FROM xbt_snatched
            ');

            list(
                $this->stats['totalSeeders'],
                $this->stats['totalLeechers'],
                $this->stats['totalPeers'],
            ) = self::$db->row('
                SELECT
                    coalesce(sum(remaining = 0), 0) as seeders,
                    coalesce(sum(remaining > 0), 0) as leechers,
                    count(*)
                FROM xbt_files_users
            ');

            $this->stats['totalPeerUsers'] = self::$db->scalar('
                SELECT count(distinct uid)
                FROM xbt_files_users xfu
                WHERE remaining = 0
                    AND active = 1
            ');

            self::$cache->cache_value(self::CACHE_KEY, $this->stats, 3600);
        }
    }
}

