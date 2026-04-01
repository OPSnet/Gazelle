<?php

namespace Gazelle\UserRank\Dimension;

class ReleaseVotes extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_releasevites';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*)
            from relay.users_main  um
            inner join tgroup_vote tv on (tv.id_user = um."ID")
            where um."Enabled" = '1'
            group by um."ID"
        END_SQL;
    }
}
