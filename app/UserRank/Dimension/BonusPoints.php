<?php

namespace Gazelle\UserRank\Dimension;

class BonusPoints extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_bonuspoint';
    }

    public function selector(): string {
        return <<<END_SQL
            select sum(bh."Price")
            from relay.users_main          um
            inner join relay.bonus_history bh on (bh."UserID" = um."ID")
            where um."Enabled" = '1'
            group by bh."UserID"
        END_SQL;
    }
}
