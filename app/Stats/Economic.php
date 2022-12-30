<?php

namespace Gazelle\Stats;

class Economic extends \Gazelle\Base {

    const CACHE_KEY = 'stats_economic';

    protected array $info;

    public function flush(): Economic {
        self::$cache->delete_value(self::CACHE_KEY);
        return $this;
    }

    public function __construct() {
        $info = self::$cache->get_value(self::CACHE_KEY);
        if ($info === false ) {
            $info = self::$db->rowAssoc("
                SELECT sum(uls.Uploaded) AS totalUpload,
                    sum(uls.Downloaded)  AS totalDownload,
                    count(*) AS totalEnabled
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = '1'
            ");

            $info['totalBounty'] = self::$db->scalar("
                SELECT SUM(Bounty) FROM requests_votes
            ");

            $info['availableBounty'] = self::$db->scalar("
                SELECT SUM(rv.Bounty)
                FROM requests_votes AS rv
                INNER JOIN requests AS r ON (r.ID = rv.RequestID)
            ");

            [$info['totalSnatches'], $info['totalTorrents']] = self::$db->row("
                SELECT sum(tls.Snatched), count(*)
                FROM torrents_leech_stats tls
            ");

            $info['totalOverallSnatches'] = self::$db->scalar("
                SELECT count(*) FROM xbt_snatched
            ");

            [$info['totalSeeders'], $info['totalLeechers'], $info['totalPeers']] = self::$db->row("
                SELECT
                    coalesce(sum(remaining = 0), 0) as seeders,
                    coalesce(sum(remaining > 0), 0) as leechers,
                    count(*)
                FROM xbt_files_users
            ");

            $info['totalPeerUsers'] = self::$db->scalar("
                SELECT count(distinct uid)
                FROM xbt_files_users xfu
                WHERE remaining = 0
                    AND active = 1
            ");

            $info = array_map('intval', $info); // some db results are stringified
            self::$cache->cache_value(self::CACHE_KEY, $info, 3600);
        }
        $this->info = $info;
    }

    public function get($key) {
        return $this->info[$key] ?? null;
    }

    public function info(): array {
        return $this->info;
    }
}

