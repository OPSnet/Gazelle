<?php

namespace Gazelle\UserRank\Dimension;

class CollageContribution extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_collagecontrib';
    }

    public function selector(): string {
        return "
            SELECT sum(contrib) FROM (
                SELECT um.ID as id, count(*) AS contrib
                FROM collages_artists c
                INNER JOIN users_main um ON (um.ID = c.UserID)
                WHERE um.Enabled = '1'
                GROUP BY um.ID
                UNION ALL
                SELECT um.ID as id, count(*) AS contrib
                FROM collages_torrents c
                INNER JOIN users_main um ON (um.ID = c.UserID)
                WHERE um.Enabled = '1'
                GROUP BY um.ID
            ) COLL
            GROUP BY id
            ORDER BY 1
            ";
    }
}
