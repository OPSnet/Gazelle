<?php

namespace Gazelle\UserRank\Dimension;

class DataUpload extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_uploadd';
    }

    public function selector(): string {
        $starting = STARTING_UPLOAD;
        return <<<END_SQL
            select uls."Uploaded" - $starting
            from relay.users_main              um
            inner join relay.users_leech_stats uls on (uls."UserID" = um."ID")
            where um."Enabled" = '1'
                and uls."Uploaded" > $starting
        END_SQL;
    }
}
