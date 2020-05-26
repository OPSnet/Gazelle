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
        parent::__construct();

        if (($this->stats = $this->cache->get_value(self::CACHE_KEY)) === false) {
            list(
                $this->stats['totalUpload'],
                $this->stats['totalDownload'],
                $this->stats['totalEnabled'],
            ) = $this->db->row('
                SELECT sum(uls.Uploaded), sum(uls.Downloaded), count(*)
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                ', '1'
            );

            $this->stats['totalBounty'] = $this->db->scalar('
                SELECT SUM(Bounty)
                FROM requests_votes
            ');

            $this->stats['availableBounty'] = $this->db->scalar('
                SELECT SUM(rv.Bounty)
                FROM requests_votes AS rv
                INNER JOIN requests AS r ON (r.ID = rv.RequestID)
            ');

            list(
                $this->stats['totalLiveSnatches'],
                $this->stats['totalTorrents'],
            ) = $this->db->row('
                SELECT sum(tls.Snatched), count(*)
                FROM torrents_leech_stats tls
            ');

            $this->stats['totalOverallSnatches'] = $this->db->scalar('
                SELECT count(*)
                FROM xbt_snatched
            ');

            list(
                $this->stats['totalSeeders'],
                $this->stats['totalLeechers'],
                $this->stats['totalPeers'],
            ) = $this->db->row('
                SELECT
                    coalesce(sum(remaining = 0), 0) as seeders,
                    coalesce(sum(remaining > 0), 0) as leechers,
                    count(*)
                FROM xbt_files_users
            ');

            $this->stats['totalPeerUsers'] = $this->db->scalar('
                SELECT count(distinct uid)
                FROM xbt_files_users xfu
                WHERE remaining = 0
                    AND active = 1
            ');

            $this->cache->cache_value(self::CACHE_KEY, $this->stats, 3600);
        }
    }
}

