<?php

namespace Gazelle\Search;

class ASN extends \Gazelle\Base {
    protected array $ipCache = [];

    public function findByASN(int $asn): array {
        return [
            'info' => $this->pg()->rowAssoc("
                SELECT a.name,
                    a.cc
                FROM geo.asn a
                WHERE a.id_asn = ?
                ", $asn
            ),
            'network' => $this->pg()->all("
                SELECT an.network
                FROM geo.asn_network an
                WHERE an.id_asn = ?
                ORDER BY an.network
                ", $asn
            )
        ];
    }

    public function findByIpList(array $ipList): array {
        if (!$ipList) {
            return [];
        }
        // filter truncated IPv6 addresses from ocelot
        $ipList = array_filter(array_unique($ipList), fn ($ip) => $ip && !str_contains($ip, '%3A'));
        $result = $this->pg()->all("
            SELECT lu.ip,
                an.network,
                coalesce(a.cc, 'XX')        AS cc,
                coalesce(a.name, 'unknown') AS name,
                a.id_asn                    AS n,
                (t.id_tor_node IS NOT NULL) AS is_tor
            FROM (SELECT unnest(ARRAY[" .  placeholders($ipList, "?::inet") . "]) as ip) AS lu
            LEFT JOIN geo.asn_network an ON (an.network >>= lu.ip)
            LEFT JOIN geo.asn a USING (id_asn)
            LEFT JOIN tor_node t ON (t.ipv4 = lu.ip)
            ", ...$ipList
        );
        $list = [];
        foreach ($result as $r) {
            $this->ipCache[$r['ip']] = $r;
            $list[$r['ip']] = $r;
        }
        return $list;
    }

    public function findByIp(string $ip): array {
        if (isset($this->ipCache[$ip])) {
            return $this->ipCache[$ip];
        }
        $result = $this->pg()->rowAssoc("
            select lu.ip,
                an.network,
                coalesce(a.cc, 'XX')        cc,
                coalesce(a.name, 'unknown') name,
                a.id_asn                    n,
                (t.id_tor_node is not null) is_tor
            from (select ?::inet as ip) as lu
            left join geo.asn_network an on (an.network >>= lu.ip)
            left join geo.asn a          using (id_asn)
            left join tor_node t         on (t.ipv4 = lu.ip)
            ", $ip
        );
        $this->ipCache[$ip] = $result;
        return $result;
    }

    public function countryISO(string $ip): string {
        return $this->findByIp($ip)['cc'] ?? 'XX';
    }

    public function searchName(string $text): array {
        return $this->pg()->all("
            SELECT id_asn,
                cc,
                name,
                count(an.*) AS total
            FROM geo.asn
            LEFT JOIN geo.asn_network AS an USING (id_asn)
            WHERE name_ts @@ plainto_tsquery('simple', ?)
            GROUP BY id_asn, cc, name
            ORDER BY ts_rank_cd(name_ts, plainto_tsquery('simple', ?))
            LIMIT 20
            ", $text, $text
        );
    }

    public function similarName(string $text): array {
        return $this->pg()->all("
            SELECT word,
                similarity(word, ?) as similarity
            FROM geo.asn_trg
            WHERE similarity(word, ?) > 0.3
            ORDER BY similarity DESC, word
            LIMIT 10
            ", $text, $text
        );
    }
}
