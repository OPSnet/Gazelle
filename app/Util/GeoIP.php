<?php

declare(strict_types=1);

namespace Gazelle\Util;

class GeoIP {
    public function __construct(protected Curl $curl) {
        // server is running on the local network
        if (defined('HTTP_PROXY')) {
            $this->curl->setUseProxy(false);
        }
    }

    public function lookup(string $ipaddr): array {
        if (GEOIP_SERVER == false) {
            return ['error' => 'GeoIP server not configured'];
        }
        if ($this->curl->fetch(GEOIP_SERVER . "/ip/$ipaddr")) {
            return json_decode($this->curl->result(), true);
        }
        return ['error' => 'GeoIP server call failed'];
    }

    public function lookupList(array $ipaddrList): array {
        if (GEOIP_SERVER == false) {
            return ['error' => 'GeoIP server not configured'];
        }
        if (
            $this->curl->fetch(GEOIP_SERVER
                . "/iplist/" . implode('+', $ipaddrList))
        ) {
            return json_decode($this->curl->result(), true);
        }
        return ['error' => 'GeoIP server call failed'];
    }

    public function countryISO(string $ipaddr): string {
        $geoip = $this->lookup($ipaddr);
        if (isset($geoip['error'])) {
            return '??';
        }
        return $geoip['asn'] === 0 ? 'XX' : $geoip['country_iso'];
    }
}
