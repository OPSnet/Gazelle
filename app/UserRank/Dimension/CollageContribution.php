<?php

namespace Gazelle\UserRank\Dimension;

class CollageContribution extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_collagecontrib';
    }

    public function selector(): string {
        return "
            SELECT sum(contrib) AS n FROM (
                SELECT um.ID as id, count(*) AS contrib
                FROM collages_artists ca
                INNER JOIN collages c ON (c.ID = ca.CollageID)
                INNER JOIN users_main um ON (um.ID = ca.UserID)
                WHERE um.Enabled = '1'
                    AND c.Deleted = '0'
                    AND c.Locked = '0'
                GROUP BY um.ID
                UNION ALL
                SELECT um.ID as id, count(*) AS contrib
                FROM collages_torrents ct
                INNER JOIN collages c ON (c.ID = ct.CollageID)
                INNER JOIN users_main um ON (um.ID = ct.UserID)
                WHERE um.Enabled = '1'
                    AND c.Deleted = '0'
                    AND c.Locked = '0'
                GROUP BY um.ID
            ) COLL
            GROUP BY id
            ORDER BY 1
        ";
    }
}
