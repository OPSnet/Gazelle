<?php

namespace Gazelle\Schedule\Tasks;

class UpdateGeoip extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            INSERT INTO users_geodistribution
                (Code, Users)
            SELECT g.Code, COUNT(u.ID) AS Users
            FROM geoip_country AS g
            INNER JOIN users_main AS u ON INET_ATON(u.IP) BETWEEN g.StartIP AND g.EndIP
            WHERE u.Enabled = '1'
            GROUP BY g.Code
            ORDER BY Users DESC
        ");
    }
}
