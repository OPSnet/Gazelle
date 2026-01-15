<?php

namespace Gazelle\UserRank\Dimension;

class DataDownload extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_download';
    }

    public function selector(): string {
        return <<<END_SQL
            select uls."Downloaded"
            from relay.users_main              um
            inner join relay.users_leech_stats uls on (uls."UserID" = um."ID")
            where um."Enabled" = '1'
                and uls."Downloaded" > 0
        END_SQL;
    }
}
