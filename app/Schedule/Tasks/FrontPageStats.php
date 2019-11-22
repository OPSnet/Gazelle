<?php

namespace Gazelle\Schedule\Tasks;

class FrontPageStats extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
                SELECT count(*)
                FROM xbt_snatched");
        list($snatchStats) = $this->db->next_record();
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

        $this->db->prepared_query("
                SELECT sum(LastAccess > now() - INTERVAL 1 DAY) AS Day,
                       sum(LastAccess > now() - INTERVAL 1 WEEK) AS Week,
                       sum(LastAccess > now() - INTERVAL 1 MONTH) AS Month
                FROM users_main
                WHERE Enabled = '1'
                    AND LastAccess > now() - INTERVAL 1 MONTH");
        $userStats = $this->db->next_record(MYSQLI_ASSOC);

        $this->cache->cache_value('stats_users', $userStats, 0);
    }
}
