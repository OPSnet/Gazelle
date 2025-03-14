<?php

namespace Gazelle\Manager;

use Gazelle\Exception\PaymentFetchForexException;

class Payment extends \Gazelle\Base {
    final protected const LIST_KEY = 'payment_list';
    final protected const RENT_KEY = 'payment_monthly_rental';
    final protected const DUE_KEY  = 'payment_due';

    public function flush(): static {
        self::$cache->delete_multi([self::LIST_KEY, self::DUE_KEY, self::RENT_KEY]);
        return $this;
    }

    public function create(
        string $text,
        string $expiry,
        float  $rent,
        string $cc,
        bool   $active,
    ): int {
        self::$db->prepared_query('
            INSERT INTO payment_reminders
                   (Text, Expiry, AnnualRent, cc, Active)
            VALUES (?,    ?,      ?,          ?,  ?)
            ', $text, $expiry, $rent, $cc, $active ? 1 : 0
        );
        $id = self::$db->inserted_id();
        $this->flush();
        return $id;
    }

    public function modify(
        int    $id,
        string $text,
        string $expiry,
        float  $rent,
        string $cc,
        bool   $active,
    ): int {
        self::$db->prepared_query("
            UPDATE payment_reminders SET
                Text = ?, Expiry = ?, AnnualRent = ?, cc = ?, Active = ?
            WHERE ID = ?
            ", $text, $expiry, $rent, $cc, $active ? 1 : 0,
            $id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function remove(int $id): int {
        self::$db->prepared_query('
            DELETE FROM payment_reminders WHERE ID = ?
            ', $id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function list(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT ID,
                    Text,
                    Expiry,
                    AnnualRent,
                    cc,
                    Active
                FROM payment_reminders
                ORDER BY Expiry
            ");
            $list = self::$db->to_array('ID', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::LIST_KEY, $list, 86400);
        }

        $rates = [];
        foreach ($list as &$l) {
            $l['Active'] = (bool)$l['Active'];
            if ($l['cc'] == 'XBT') {
                $l['fiatRate'] = 1.0;
                $l['Rent'] = $l['btcRent'] = sprintf('%0.6f', $l['AnnualRent']);
            } else {
                if (!isset($rates[$l['cc']])) {
                    $l['fiatRate'] = (float)self::$db->scalar("
                        SELECT rate
                        FROM xbt_forex
                        WHERE cc = ?
                        ORDER BY forex_date DESC
                        LIMIT 1
                        ", $l['cc']
                    );
                    if (!$l['fiatRate']) {
                        throw new PaymentFetchForexException(sprintf('XBT id=%d cc=%s', $l['ID'], $l['cc']));
                    }
                    $rates[$l['cc']] = $l['fiatRate'];
                } else {
                    $l['fiatRate'] = $rates[$l['cc']];
                }
                $l['Rent'] = sprintf('%0.2f', $l['AnnualRent']);
                $l['btcRent'] = sprintf('%0.6f', $l['AnnualRent'] / $l['fiatRate']);
            }
        }
        return $list;
    }

    public function monthlyRental(): float {
        $rental = self::$cache->get_value(self::RENT_KEY);
        if ($rental === false) {
            $rental = (float)self::$db->scalar("
                SELECT sum(p.AnnualRent/coalesce(CUR.rate, 1.0)) / 12
                FROM payment_reminders p
                LEFT JOIN (
                    SELECT f.cc,
                        f.rate
                    FROM xbt_forex f
                    INNER JOIN (
                        SELECT cc,
                            max(forex_date) AS forex_date
                        FROM xbt_forex
                        GROUP BY cc
                    ) LATEST using (cc, forex_date)
                ) CUR USING (cc)
                WHERE p.active = 1;
            ");
            self::$cache->cache_value(self::RENT_KEY, $rental, 86400);
        }
        /**
         * FIXME!
         * See: https://github.com/php-memcached-dev/php-memcached/issues/500
         */
        return abs($rental);
    }

    public function monthlyPercent(\Gazelle\Manager\Donation $donorMan): int {
        $monthlyRental = $this->monthlyRental();
        return $monthlyRental == 0.0
            ? 100
            : min(100, (int)(($donorMan->totalMonth(1) / $monthlyRental) * 100));
    }

    public function due(): array {
        $due = self::$cache->get_value(self::DUE_KEY);
        if ($due === false) {
            self::$db->prepared_query('
                SELECT Text, Expiry
                FROM payment_reminders
                WHERE Active = 1 AND Expiry < now() + INTERVAL 1 WEEK
                ORDER BY Expiry
            ');
            $due = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::DUE_KEY, $due, 3600);
        }
        return $due;
    }

    public function soon(): array {
        return self::$db->rowAssoc("
            SELECT count(*) as total,
                min(Expiry) as next
            FROM payment_reminders
            WHERE Active = 1 AND Expiry < now() + INTERVAL 1 WEEK
        ");
    }
}
