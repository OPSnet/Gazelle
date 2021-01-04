<?php

namespace Gazelle\UserRank\Dimension;

class Uploads extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_uploads';
    }

    public function selector(): string {
        return "
            SELECT DISTINCT n FROM (
                SELECT count(*) AS n
                FROM users_main AS um
                INNER JOIN torrents AS t ON (t.UserID = um.ID)
                WHERE um.Enabled = '1'
                GROUP BY um.ID
            ) C
            ORDER BY 1
            ";
    }
}
