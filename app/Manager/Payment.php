<?php

namespace Gazelle\Manager;

use Gazelle\Exception\PaymentFetchForexException;

/* pg_wrapper returns the `date` datatype as \DateTimeImmutable
 * so ensure that all uses are truncated to midnight.
 */

class Payment extends \Gazelle\Base {
    final protected const LIST_KEY = 'payment_list';
    final protected const RENT_KEY = 'payment_monthly_rental';

    public function flush(): static {
        self::$cache->delete_multi([self::LIST_KEY, self::RENT_KEY]);
        return $this;
    }

    public function create(
        string             $text,
        \DateTimeImmutable $expiry,
        float              $rent,
        string             $cc,
        bool               $active,
    ): int {
        $id = $this->pg()->scalarex("
            insert into payment
                   (name, expiry, annual_rent, currency, active)
            values ($1,   $2,     $3,          $4,       $5)
            returning id_payment
            ", $text, $expiry->modify('midnight'), $rent, $cc, $active
        );
        $this->flush();
        return $id;
    }

    public function modify(
        int                $id,
        string             $text,
        \DateTimeImmutable $expiry,
        float              $rent,
        string             $cc,
        bool               $active,
    ): int {
        $affected = $this->pg()->query("
            update payment set
                name        = $1,
                expiry      = $2,
                annual_rent = $3,
                currency    = $4,
                active      = $5
            where id_payment = $6
            ", $text, $expiry->modify('midnight'), $rent, $cc, $active,
            $id
        );
        $this->flush();
        return $affected;
    }

    public function remove(int $id): int {
        $affected = $this->pg()->query('
            delete from payment where id_payment = $1
            ', $id
        );
        $this->flush();
        return $affected;
    }

    public function list(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if ($list === false) {
            $list = $this->pg()->allex('
                select id_payment as id,
                    name,
                    expiry,
                    annual_rent,
                    currency,
                    active
                from payment
                order by expiry
            ');
            self::$cache->cache_value(self::LIST_KEY, $list, 86400);
        }

        $rates = [];
        foreach ($list as &$l) {
            if ($l['currency'] === 'XBT') {
                $l['fiatRate'] = 1.0;
                $l['rent'] = $l['btcRent'] = (float)sprintf('%0.6f', $l['annual_rent']);
            } else {
                if (!isset($rates[$l['currency']])) {
                    $l['fiatRate'] = (float)self::$db->scalar("
                        SELECT rate
                        FROM xbt_forex
                        WHERE cc = ?
                        ORDER BY forex_date DESC
                        LIMIT 1
                        ", $l['currency']
                    );
                    if (!$l['fiatRate']) {
                        throw new PaymentFetchForexException(
                            sprintf('XBT id=%d cc=%s', $l['id'], $l['currency'])
                        );
                    }
                    $rates[$l['currency']] = $l['fiatRate'];
                } else {
                    $l['fiatRate'] = $rates[$l['currency']];
                }
                $l['rent'] = sprintf('%0.2f', $l['annual_rent']);
                $l['btcRent'] = sprintf('%0.6f', $l['annual_rent'] / $l['fiatRate']);
            }
        }
        return $list;
    }

    public function monthlyRental(): float {
        $rental = self::$cache->get_value(self::RENT_KEY);
        if ($rental === false) {
            $rental = (float)$this->pg()->scalarex("
                select sum(p.annual_rent/coalesce(CUR.rate, 1.0)) / 12
                from payment p
                left join (
                    select f.cc,
                        f.rate
                    from relay.xbt_forex f
                    inner join (
                        select cc,
                            max(forex_date) AS forex_date
                        from relay.xbt_forex
                        group by cc
                    ) LATEST using (cc, forex_date)
                ) CUR on (CUR.cc::text::currency_t = p.currency)
                where p.active is true
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

    public function soon(): array {
        return $this->pg()->rowAssocex("
            select count(*) as total,
                min(expiry) as next
            from payment
            where expiry < now() + interval '1 week'
                and active is true
        ");
    }
}
