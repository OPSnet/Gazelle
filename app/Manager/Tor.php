<?php

namespace Gazelle\Manager;

class Tor extends \Gazelle\Base {
    public function __construct(
        protected \Gazelle\DB\Pg $pg = new \Gazelle\DB\Pg(GZPG_DSN)
    ) { }

    public function add(string $text): int {
        if (!preg_match_all('/(\d{1,3}(?:\.\d{1,3}){3})/', $text, $match)) {
            return 0;
        }
        $quad = array_unique($match[0]);

        $this->pg->pdo()->beginTransaction();
        $this->pg->pdo()->query("
            CREATE TEMPORARY TABLE tor_node_new (ipv4 inet)
        ");
        $this->pg->prepared_query(
            sprintf("
                INSERT INTO tor_node_new (ipv4) VALUES %s
                ", placeholders($quad, '(?)')
            ), ...$quad
        );

        $st = $this->pg->pdo()->query("
            DELETE FROM tor_node
            WHERE ipv4 NOT IN (
                SELECT ipv4 FROM tor_node_new
            )
        ");
        $changed = -$st->rowCount();
        $this->pg->pdo()->query("
            DELETE FROM tor_node_new
            WHERE ipv4 IN (
                SELECT ipv4 FROM tor_node
            )
        ");
        $this->pg->pdo()->query("
            INSERT INTO tor_node (ipv4)
                SELECT ipv4 FROM tor_node_new
        ");
        $changed += $st->rowCount();
        $this->pg->pdo()->commit();

        return $changed;
    }

    public function exitNodeList(): array {
        return $this->pg->all("
            SELECT t.ipv4,
                t.created,
                coalesce(a.cc, 'XX') as cc,
                coalesce(a.name, 'unknown') as name,
                a.id_asn
            FROM tor_node t
            LEFT JOIN geo.asn_network an ON (an.network >> t.ipv4)
            LEFT JOIN geo.asn a USING (id_asn)
            ORDER BY 1
        ");
    }

    public function isExitNode(string $ip): bool {
        return (bool)$this->pg->scalar("
            SELECT 1 FROM tor_node WHERE ipv4 = ?
            ", $ip
        );
    }
}
