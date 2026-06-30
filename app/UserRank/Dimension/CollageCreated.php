<?php

namespace Gazelle\UserRank\Dimension;

class CollageCreated extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_collagecontrib';
    }

    public function selector(): string {
        return <<<END_SQL
            select usm.collage_created
            from relay.users_main um
            inner join user_summary_misc usm on (usm.id_user = um."ID")
            where um."Enabled" = '1'
                and usm.collage_created > 0
        END_SQL;
    }
}
