<?php

namespace Gazelle\UserRank\Dimension;

class RequestsFilled extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_requestsfilled';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*) AS n
            from relay.users_main um
            inner join request    r on (r.id_filler = um."ID")
            where um."Enabled" = '1'
            group by um."ID"
        END_SQL;
    }
}
