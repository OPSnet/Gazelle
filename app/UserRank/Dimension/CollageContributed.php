<?php

namespace Gazelle\UserRank\Dimension;

class CollageContributed extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_collagecontrib';
    }

    public function selector(): string {
        return <<<END_SQL
            select usm.collage_contrib
            from relay.users_main um
            inner join user_summary_misc usm on (usm.id_user = um."ID")
            where um."Enabled" = '1'
                and usm.collage_contrib > 0
        END_SQL;
    }
}
