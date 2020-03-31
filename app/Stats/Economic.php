<?php

namespace Gazelle\Stats;

class Economic {
    /** @var \DB_MYSQL */
    protected $db;
    /** @var \CACHE */
    protected $cache;

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

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;

        if (($this->stats = $this->cache->get_value(self::CACHE_KEY)) === false) {
            list(
                $this->stats['totalUpload'],
                $this->stats['totalDownload'],
                $this->stats['totalEnabled'],
            ) = $this->db->lookup('
                SELECT sum(uls.Uploaded), sum(uls.Downloaded), count(*)
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                ', '1'
            );

            list($this->stats['totalBounty']) = $this->db->lookup('
                SELECT SUM(Bounty)
                FROM requests_votes
            ');

            list($this->stats['availableBounty']) = $this->db->lookup('
                SELECT SUM(rv.Bounty)
                FROM requests_votes AS rv
                INNER JOIN requests AS r ON (r.ID = rv.RequestID)
            ');

            list(
                $this->stats['totalLiveSnatches'],
                $this->stats['totalTorrents'],
            ) = $this->db->lookup('
                SELECT sum(tls.Snatched), count(*)
                FROM torrents_leech_stats tls
            ');

            list($this->stats['totalOverallSnatches']) = $this->db->lookup('
                SELECT count(*)
                FROM xbt_snatched
            ');

            list(
                $this->stats['totalSeeders'],
                $this->stats['totalLeechers'],
                $this->stats['totalPeers'],
            ) = $this->db->lookup('
                SELECT
                    coalesce(sum(remaining = 0), 0) as seeders,
                    coalesce(sum(remaining > 0), 0) as leechers,
                    count(*)
                FROM xbt_files_users
            ');

            list($this->stats['totalPeerUsers']) = $this->db->lookup('
                SELECT count(distinct uid)
                FROM xbt_files_users xfu
                WHERE remaining = 0
                    AND active = 1
            ');

            $this->cache->cache_value(self::CACHE_KEY, $this->stats, 3600);
        }
    }
}

