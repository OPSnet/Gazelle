<?php

namespace Gazelle\Stats;

class Users extends \Gazelle\Base {
    /**
     * The annual flow of users: people registered and disabled
     * @return array keyed by month [month, joined, disabled]
     */
    public function flow(): array {
        if (!$flow = $this->cache->get_value('stat-user-timeline')) {
            /* Mysql does not implement a full outer join, so if there is a month with
             * no joiners, any banned users in that same month will not appear.
             * Mysql does not implement sequence generators as in Postgres, so if there
             * is a month without any joiners or banned, it will not appear at all.
             * Deal with it. - Spine
             */
            $this->db->prepared_query("
                SELECT J.Mon, J.n as Joined, coalesce(D.n, 0) as Disabled
                FROM (
                    SELECT DATE_FORMAT(JoinDate,'%Y%m') as M, DATE_FORMAT(JoinDate, '%b %Y') AS Mon, count(*) AS n
                    FROM users_info
                    WHERE JoinDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                        AND last_day(now()) - INTERVAL 1 MONTH
                    GROUP BY M) J
                LEFT JOIN (
                    SELECT DATE_FORMAT(BanDate, '%Y%m') AS M, DATE_FORMAT(BanDate, '%b %Y') AS Mon, count(*) AS n
                    FROM users_info
                    WHERE BanDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                        AND last_day(now()) - INTERVAL 1 MONTH
                    GROUP By M
                ) D USING (M)
                ORDER BY J.M;
            ");
            $flow = $this->db->to_array('Mon');
            $this->cache->cache_value('stat-user-timeline', $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
        }
        return $flow ?: [];
    }

    /**
     * Count of users aggregated by primary class
     * @return array [class name, user count]
     */
    public function classDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-class')) {
            $this->db->prepared_query("
                SELECT p.Name, count(*) AS Users
                FROM users_main AS m
                INNER JOIN permissions AS p ON (m.PermissionID = p.ID)
                WHERE m.Enabled = '1'
                GROUP BY p.Name
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array('Name');
            $this->cache->cache_value('stat-user-class', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Count of users aggregated by OS platform
     * @return array [platform, user count]
     */
    public function platformDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-platform')) {
            $this->db->prepared_query("
                SELECT OperatingSystem, count(*) AS Users
                FROM users_sessions
                GROUP BY OperatingSystem
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array();
            $this->cache->cache_value('stat-user-platform', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Count of users aggregated by browser
     * @return array [browser, user count]
     */
    public function browserDistribution(): array {
        if (!$dist = $this->cache->get_value('stat-user-browser')) {
            $this->db->prepared_query("
                SELECT Browser, count(*) AS Users
                FROM users_sessions
                GROUP BY Browser
                ORDER BY Users DESC
            ");
            $dist = $this->db->to_array();
            $this->cache->cache_value('stat-user-browser', $dist, 86400);
        }
        return $dist ?: [];
    }

    /**
     * Country aggregates.
     * TODO: this is really fucked
     *
     * @return array List of country
     * @return array Country rank
     * @return array Country user total
     * @return int Country with least users
     * @return int Country with most users
     * @return int Log increments
     */
    public function geodistribution(): array {
        if (![$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements] = $this->cache->get_value('geodistribution')) {
            $this->db->prepared_query("
                SELECT Code, Users FROM users_geodistribution
            ");
            $Data = $this->db->to_array();
            $Count = $this->db->record_count() - 1;

            if ($Count < 30) {
                $CountryMinThreshold = $Count;
            } else {
                $CountryMinThreshold = 30;
            }

            $CountryMax = ceil(log(Max(1, $Data[0][1])) / log(2)) + 1;
            $CountryMin = floor(log(Max(1, $Data[$CountryMinThreshold][1])) / log(2));

            $CountryRegions = ['RS' => ['RS-KM']]; // Count Kosovo as Serbia as it doesn't have a TLD
            foreach ($Data as $Key => $Item) {
                [$Country, $UserCount] = $Item;
                $Countries[] = $Country;
                $CountryUsers[] = number_format((((log($UserCount) / log(2)) - $CountryMin) / ($CountryMax - $CountryMin)) * 100, 2);
                $Rank[] = round((1 - ($Key / $Count)) * 100);

                if (isset($CountryRegions[$Country])) {
                    foreach ($CountryRegions[$Country] as $Region) {
                        $Countries[] = $Region;
                        $Rank[] = end($Rank);
                    }
                }
            }

            for ($i = $CountryMin; $i <= $CountryMax; $i++) {
                $LogIncrements[] = \Format::human_format(pow(2, $i));
            }
            $this->cache->cache_value('geodistribution', [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements], 86400 * 3);
        }
        return [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements];
    }
}
