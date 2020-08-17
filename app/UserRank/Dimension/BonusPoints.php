<?php

namespace Gazelle\UserRank\Dimension;

class BonusPoints extends \Gazelle\UserRank\AbstractUserRank {

    public function cacheKey(): string {
        return 'rank_data_bonuspoint';
    }

    public function selector(): string {
        return "
            SELECT sum(bh.Price)
            FROM bonus_history bh
            INNER JOIN users_main AS um ON (um.ID = bh.UserID)
            GROUP BY UserID
            ORDER BY 1
            ";
    }
}
