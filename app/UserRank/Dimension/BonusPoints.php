<?php

namespace Gazelle\UserRank\Dimension;

class BonusPoints extends \Gazelle\UserRank\AbstractUserRank {
    public function cacheKey(): string {
        return 'rank_data_bonuspoint';
    }

    public function selector(): string {
        return <<<END_SQL
            select sum(hb.price)
            from relay.users_main    um
            inner join history_bonus hb on (hb.id_user = um."ID")
            where um."Enabled" = '1'
            group by hb.id_user
        END_SQL;
    }
}
