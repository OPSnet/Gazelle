<?php

namespace Gazelle\UserRank\Dimension;

class BountySpent extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_bountyspent';
    }

    public function selector(): string {
        return "
            SELECT DISTINCT n FROM (
                SELECT sum(rv.Bounty) AS n
                FROM users_main AS um
                INNER JOIN requests_votes AS rv ON (rv.UserID = um.ID)
                WHERE um.Enabled = '1'
                GROUP BY um.ID
            ) C
            ORDER BY 1
            ";
    }
}
