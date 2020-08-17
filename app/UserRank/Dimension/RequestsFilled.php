<?php

namespace Gazelle\UserRank\Dimension;

class RequestsFilled extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_requestsfilled';
    }

    public function selector(): string {
        return "
            SELECT count(*)
            FROM users_main AS um
            INNER JOIN requests AS r ON (r.FillerID = um.ID)
            WHERE um.Enabled = '1'
            GROUP BY um.ID
            ORDER BY 1
            ";
    }
}
