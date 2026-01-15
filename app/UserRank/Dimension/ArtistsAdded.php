<?php

namespace Gazelle\UserRank\Dimension;

class ArtistsAdded extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_artistsadded';
    }

    public function selector(): string {
        return <<<END_SQL
            select count(*)
            from relay.users_main             um
            inner join relay.torrents_artists ta on (ta."UserID" = um."ID")
            inner join relay.torrents_group   tg on (tg."ID" = ta."GroupID")
            inner join relay.torrents         t  on (t."GroupID" = tg."ID")
            where um."Enabled" = '1'
                and t."UserID" != ta."UserID"
            group by tg."ID"
            order by 1
        END_SQL;
    }
}
