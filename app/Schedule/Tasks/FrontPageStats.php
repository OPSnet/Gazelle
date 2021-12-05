<?php

namespace Gazelle\Schedule\Tasks;

class FrontPageStats extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $snatchStats = self::$db->scalar("
            SELECT count(*)
            FROM xbt_snatched"
        );
        self::$cache->cache_value('stats_snatches', $snatchStats, 0);

        self::$db->prepared_query("
            SELECT IF(remaining = 0, 'Seeding', 'Leeching') AS Type, count(*)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type");
        $peerCount = self::$db->to_array(0, MYSQLI_NUM, false);
        $seederCount = isset($peerCount['Seeding'][1]) ? $peerCount['Seeding'][1] : 0;
        $leecherCount = isset($peerCount['Leeching'][1]) ? $peerCount['Leeching'][1] : 0;
        self::$cache->cache_value('stats_peers', [$leecherCount, $seederCount], 0);

        $userMan = new \Gazelle\Manager\User;
        $stats = $userMan->globalActivityStats();
        $this->processed = $stats['Day']; /* why not? */
    }
}
