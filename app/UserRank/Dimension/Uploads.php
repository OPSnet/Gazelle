<?php

namespace Gazelle\UserRank\Dimension;

class Uploads extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_uploads';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*)
            from relay.users_main     um
            inner join relay.torrents t on (t."UserID" = um."ID")
            where um."Enabled" = '1'
            group by um."ID"
        END_SQL;
    }
}
