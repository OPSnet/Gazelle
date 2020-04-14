<?php

namespace Gazelle\Schedule\Tasks;

class FrontPageStats extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $snatchStats = $this->db->scalar("
            SELECT count(*)
            FROM xbt_snatched"
        );
        $this->cache->cache_value('stats_snatches', $snatchStats, 0);

        $this->db->prepared_query("
            SELECT IF(remaining = 0, 'Seeding', 'Leeching') AS Type, count(*)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type");
        $peerCount = $this->db->to_array(0, MYSQLI_NUM, false);
        $seederCount = isset($peerCount['Seeding'][1]) ? $peerCount['Seeding'][1] : 0;
        $leecherCount = isset($peerCount['Leeching'][1]) ? $peerCount['Leeching'][1] : 0;
        $this->cache->cache_value('stats_peers', [$leecherCount, $seederCount], 0);

        $stats = \Gazelle\User::globalActivityStats($this->db, $this->cache);
        $this->processed = $stats['Day']; /* why not? */
    }
}
