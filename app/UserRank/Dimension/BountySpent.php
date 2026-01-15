<?php

namespace Gazelle\UserRank\Dimension;

class BountySpent extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_bountyspent';
    }

    public function selector(): string {
        return <<<END_SQL
            select sum(rv.bounty)
            from relay.users_main um
            inner join request_vote rv on (rv.id_user = um."ID")
            where um."Enabled" = '1'
            group by rv.id_user
        END_SQL;
    }
}
