<?php

namespace Gazelle\Stats;

class Bonus extends \Gazelle\Base {

    /**
     * Get the total purchases of all items
     *
     * @return array of [title, total]
     */
    public function itemPurchase(): array {
        self::$db->prepared_query("
            SELECT bi.ID as id,
                bi.Title AS title,
                count(bh.ID) AS total
            FROM bonus_item bi
            LEFT JOIN bonus_history bh ON (bh.ItemID = bi.ID)
            GROUP BY bi.Title
            ORDER BY bi.sequence
        ");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Get the expenditure on bonus shop items over an interval of time,
     * between now - (offset + length) to now - offset.
     * Examples:
     *  ('MONTH', 0,  1) = Beginning now, one previous month of expenditure
     *  ('MONTH', 1,  1) = Beginning one month ago, the prior month of expenditure
     *  ('WEEK', 26,  4) = Beginning 26 weeks ago, 4 weeks prior expenditure
     *
     * @return array of array of [title, total] aggregated over interval range
     */
    public function expenditureRange(string $interval, int $offset, int $length): array {
        self::$db->prepared_query("
            SELECT bi.ID AS id,
                bi.Title AS title,
                count(bh.ItemID) AS total
            FROM bonus_item bi
            LEFT JOIN bonus_history bh ON (bh.ItemID = bi.ID)
            WHERE (
                bh.ItemID IS NULL
                OR
                bh.PurchaseDate BETWEEN (now() - INTERVAL ? {$interval}) AND (now() - INTERVAL ? {$interval})
            )
            GROUP BY bi.ID
            ORDER BY bi.sequence
            ", ($offset + $length), $offset
        );
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Get the accrual of bonus points over an interval of time.
     * Examples:
     *  ('MONTH', 0,  1) = Beginning now, one previous month of acquisition
     *  ('MONTH', 1,  1) = Beginning one month ago, the prior month of acquisition
     *  ('WEEK', 26,  4) = Beginning 26 weeks ago, 4 weeks prior acquisition
     *
     * @param string $interval Mysql interval (HOUR, DAY, MONTH, YEAR...)
     * @param int $offset number of intervals back in time
     * @param int $length number of intervals
     * @return array of array of [title, total] aggregated over interval range
     */
    public function accrualRange(string $interval, int $offset, int $length): array {
        switch ($interval) {
            case 'SECOND':
            case 'MINUTE':
            case 'HOUR':
                $table = 'users_stats_daily';
                break;
            case 'DAY':
            case 'WEEK':
                $table = 'users_stats_monthly';
                break;
            default:
                $table = 'users_stats_yearly';
                break;
        }
        return self::$db->rowAssoc("
            SELECT us.Time AS `date`,
                sum(us.BonusPoints) AS total
            FROM $table us
            INNER JOIN (
                SELECT UserID,
                    max(Time) as Time
                FROM $table
                WHERE Time BETWEEN (now() - INTERVAL ? {$interval}) AND (now() - INTERVAL ? {$interval})
                GROUP BY UserID
            ) HIST USING (UserID, Time)
            GROUP BY us.Time
            ", ($offset + $length), $offset
        ) ?? ['date' => 0, 'total' => 0];
    }

    /**
     * N members with the most accrued bonus points
     *
     * @return array of array of [user_id, total]
     */
    public function topHoarders(int $n): array {
        self::$db->prepared_query("
            SELECT user_id,
                points AS total
            FROM user_bonus
            ORDER BY points DESC
            LIMIT ?
            ", $n
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * N members with the most bonus points spent
     *
     * @return array of array of [user_id, total]
     */
    public function topSpenders(int $n): array {
        self::$db->prepared_query("
            SELECT UserID AS user_id,
                sum(Price) AS total
            FROM bonus_history
            GROUP BY user_id
            ORDER BY total DESC
            LIMIT ?
            ", $n
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * N members with the most bonus points contributed to bonus pools
     *
     * @return array of array of [user_id, total]
     */
    public function topPoolContributors(int $n): array {
        self::$db->prepared_query("
            SELECT user_id,
                sum(amount_sent) AS total
            FROM bonus_pool_contrib
            GROUP BY user_id
            ORDER BY total DESC
            LIMIT ?
            ", $n
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * N members with the most bonus points spent in aggregate
     *
     * @return array of array of [user_id, total]
     */
    public function topAggregateSpenders(int $n): array {
        self::$db->prepared_query("
            select user_id,
                sum(total) as total
            FROM (
                SELECT user_id,
                    sum(amount_sent) AS total
                FROM bonus_pool_contrib
                GROUP BY user_id
            UNION ALL
                SELECT UserID AS user_id,
                    sum(Price) AS total
                FROM bonus_history
                GROUP BY user_id
            ) SPEND
            GROUP BY user_id
            ORDER BY total DESC
            LIMIT ?
            ", $n
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
