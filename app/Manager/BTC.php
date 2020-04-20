<?php

namespace Gazelle\Manager;

class BTC {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    const CACHE_KEY = 'btc_rate_%s';

    /* Coinbase quotes have a 1% fee, but we lose more in tumbling, so whatever */
    const FX_QUOTE_URL = 'https://api.coinbase.com/v2/prices/BTC-%s/buy';

    public function __construct (\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    /* Fetch the current BTC rate for a given currency code (ISO 4217)
     *
     * @param string $CC Currency Code
     * @return float current rate, or null if API endpoint cannot be reached or is in error.
     */
    public function fetchRate(string $CC) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => sprintf(self::FX_QUOTE_URL, $CC),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        if (defined('HTTP_PROXY')) {
            curl_setopt_array($curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY => HTTP_PROXY,
            ]);
        }

        $result = curl_exec($curl);
        if ($result === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            return null;
        }

        // {"data":{"base":"BTC","currency":"USD","amount":"8165.93"}}
        $payload = json_decode($result);
        return $payload->data->amount;
    }

    public function saveRate(string $CC, float $rate) {
        $this->db->prepared_query('
            INSERT INTO btc_forex
                   (cc, rate)
            VALUES (?,  ?)
            ', $CC, $rate
        );
        return $this->db->affected_rows();
    }

    public function latestRate(string $CC) {
        $key = sprintf(self::CACHE_KEY, $CC);
        if (($rate = $this->cache->get_value($key)) === false) {
            $rate = $this->db->scalar('
                SELECT rate
                FROM btc_forex
                WHERE forex_date > now() - INTERVAL 6 HOUR
                    AND cc = ? GROUP BY cc
                ORDER BY forex_date DESC
                LIMIT 1
                ', $CC
            );
            if (is_null($rate)) {
                $rate = $this->fetchRate($CC);
                $this->saveRate($CC, $rate);
            }
            $this->cache->cache_value($key, $rate, 3600 * 6);
        }
        return $rate;
    }

}
