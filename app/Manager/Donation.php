<?php

namespace Gazelle\Manager;

class Donation extends \Gazelle\Base {
    public function rewardTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_main AS um
            INNER JOIN users_donor_ranks AS d ON (d.UserID = um.ID)
            INNER JOIN donor_rewards AS r ON (r.UserID = um.ID)
        ");
    }

    public function rewardPage(?string $search, int $limit, int $offset): array {
        $args = [$limit, $offset];
        if (is_null($search)) {
            $where = '';
        } else {
            $where = "WHERE um.username REGEXP ?";
            array_unshift($args, $search);
        }
        self::$db->prepared_query("
            SELECT um.Username,
                d.UserID AS user_id,
                d.donor_rank,
                if(hidden=0, 'No', 'Yes') AS hidden,
                d.DonationTime AS donation_time,
                r.IconMouseOverText AS icon_mouse,
                r.AvatarMouseOverText AS avatar_mouse,
                r.CustomIcon AS custom_icon,
                r.SecondAvatar AS second_avatar,
                r.CustomIconLink AS custom_link
            FROM users_main AS um
            INNER JOIN users_donor_ranks AS d ON (d.UserID = um.ID)
            INNER JOIN donor_rewards AS r ON (r.UserID = um.ID)
            $where ORDER BY d.donor_rank DESC, d.DonationTime ASC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function totalMonth(int $month): float {
        $key = "donations_month_$month";
        $donations = self::$cache->get_value($key);
        if ($donations === false) {
            $donations = (float)self::$db->scalar("
                SELECT sum(xbt)
                FROM donations
                WHERE time >= CAST(DATE_FORMAT(now() ,'%Y-%m-01') as DATE) - INTERVAL ? MONTH
                ", $month - 1
            );
            self::$cache->cache_value($key, $donations, 3600 * 36);
        }
        return abs($donations); // https://github.com/php-memcached-dev/php-memcached/issues/500
    }

    public function expireRanks(): int {
        self::$db->prepared_query("
            SELECT UserID
            FROM users_donor_ranks
            WHERE donor_rank > 1
                AND SpecialRank != 3
                AND RankExpirationTime < now() - INTERVAL 766 HOUR
        "); // 2 hours less than 32 days to account for schedule run times
        $userIds = [];
        while ([$id] = self::$db->next_record()) {
            self::$cache->delete_multi(["donor_info_$id", "donor_title_$id", "donor_profile_rewards_$id"]);
            $userIds[] = $id;
        }
        if ($userIds) {
            self::$db->prepared_query("
                UPDATE users_donor_ranks SET
                    donor_rank = donor_rank - 1,
                    RankExpirationTime = now()
                WHERE donor_rank > 1
                    AND UserID IN (" . placeholders($userIds) . ")
                ", ...$userIds
            );
        }
        return count($userIds);
    }

    public function grandTotal(): float {
        return (float)self::$db->scalar("
            SELECT SUM(xbt) FROM donations
        ");
    }

    public function timeline(): array {
        self::$db->prepared_query("
            SELECT date_format(Time,'%b %Y') AS Month,
                sum(xbt) as Amount
            FROM donations
            GROUP BY Month
            ORDER BY Time DESC
            LIMIT 0, 17
        ");
        $timeline =  array_reverse(self::$db->to_array(false, MYSQLI_ASSOC, false));
        foreach ($timeline as &$t) {
            $t['Amount'] = (float)$t['Amount'];
        }
        return $timeline;
    }

    public function topDonorList(int $limit, \Gazelle\Manager\User $userMan): array {
        self::$db->prepared_query("
            SELECT UserID
            FROM users_donor_ranks
            WHERE TotalRank > 0
            ORDER BY TotalRank DESC, DonationTime ASC
            LIMIT ?
            ", $limit
        );
        return array_map(
            fn ($u) => new \Gazelle\User\Donor($u),
            array_filter(
                array_map(
                    fn ($id) => $userMan->findById($id),
                    self::$db->collect(0, false)
                ),
                fn($u) => $u
            )
        );
    }
}
