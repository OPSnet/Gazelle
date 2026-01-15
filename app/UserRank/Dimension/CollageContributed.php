<?php

namespace Gazelle\UserRank\Dimension;

class CollageContributed extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_collagecontrib';
    }

    public function selector(): string {
        return <<<END_SQL
            select collage_contrib
            from relay.users_main um
            inner join relay.user_summary us on (us.user_id = um."ID")
            where um."Enabled" = '1'
                and collage_contrib > 0
        END_SQL;
    }
}
