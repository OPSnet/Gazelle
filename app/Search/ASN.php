<?php

namespace Gazelle\Search;

class ASN extends \Gazelle\Base {
    protected array $ipCache = [];

    public function findByASN(int $asn): array {
        return [
            'info' => $this->pg()->rowAssocex("
                select a.name,
                    a.cc
                from geo.asn a
                where a.id_asn = $1
                ", $asn
            ),
            'network' => $this->pg()->allex("
                select an.network
                from geo.asn_network an
                where an.id_asn = $1
                order by an.network
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
        $result = $this->pg()->allex("
            select lu.ip,
                an.network,
                coalesce(a.cc, 'XX')        as cc,
                coalesce(a.name, 'unknown') as name,
                a.id_asn                    as n,
                (t.id_tor_node is not null) as is_tor
            from (select unnest(array[" .  placeholders_pg($ipList, "(%s)::inet") . "]) as ip) as lu
            left join geo.asn_network an on (an.network >>= lu.ip)
            left join geo.asn         a using (id_asn)
            left join tor_node        t on (t.ipv4 = lu.ip)
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
        $result = $this->pg()->rowAssocex("
            select lu.ip,
                an.network,
                coalesce(a.cc, 'XX')        cc,
                coalesce(a.name, 'unknown') name,
                a.id_asn                    n,
                (t.id_tor_node is not null) is_tor
            from (select $1::inet as ip) as lu
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
        return $this->pg()->allex("
            select id_asn,
                cc,
                name,
                count(an.*) AS total
            from geo.asn
            left join geo.asn_network AS an USING (id_asn)
            where name_ts @@ plainto_tsquery('simple', $1)
            group by id_asn, cc, name
            order by ts_rank_cd(name_ts, plainto_tsquery('simple', $1))
            limit 20
            ", $text
        );
    }

    public function similarName(string $text): array {
        return $this->pg()->allex("
            select word,
                similarity(word, $1) as similarity
            from geo.asn_trg
            where similarity(word, $1) > 0.3
            order by similarity desc, word
            limit 10
            ", $text
        );
    }
}
