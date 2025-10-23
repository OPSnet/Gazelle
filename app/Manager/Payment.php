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
        $id = $this->pg()->insert("
            insert into payment
                   (name, expiry, annual_rent, currency, active)
            VALUES (?,    ?,      ?,          ?,  ?)
            ", $text, $expiry, $rent, $cc, $active ? 't' : 'f'
        );
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
        $result = $this->pg()->executeParams("
            update payment set
                name = $1, expiry = $2, annual_rent = $3, currency = $4, active = $5
            where id_payment = $6
            ", $text, $expiry, $rent, $cc, $active ? 't' : 'f',
            $id
        );
        $affected = $result->getAffectedRows();
        $this->flush();
        return $affected;
    }

    public function remove(int $id): int {
        $result = $this->pg()->executeParams('
            delete from payment where id_payment = $1
            ', $id
        );
        $affected = $result->getAffectedRows();
        $this->flush();
        return $affected;
    }

    public function list(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if ($list === false) {
            $list = $this->pg()->all('
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
            $rental = (float)$this->pg()->scalar("
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

    public function due(): array {
        $due = self::$cache->get_value(self::DUE_KEY);
        if ($due === false) {
            $due = $this->pg()->all("
                select name, expiry
                from payment
                where expiry < now() + '1 week'::interval
                    and active is true
                order by expiry
            ");
            self::$cache->cache_value(self::DUE_KEY, $due, 3600);
        }
        return $due;
    }

    public function soon(): array {
        return $this->pg()->rowAssoc("
            select count(*) as total,
                min(expiry) as next
            from payment
            where expiry < now() + '1 week'::interval
                and active is true
        ");
    }
}
