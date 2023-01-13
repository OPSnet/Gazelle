<?php

namespace Gazelle\Manager;

class SSLHost extends \Gazelle\Base {
    public function __construct(
        protected \Gazelle\DB\Pg $pg = new \Gazelle\DB\Pg(GZPG_DSN)
    ) {}

    public function lookup(string $hostname, int $port): array {
        if ($port <= 0 || !preg_match('/^(?:[\w-]+)(?:\.[\w-]+)+$/', $hostname)) {
            return [];
        }
        $context = [
            "ssl"  => ["capture_peer_cert" => TRUE],
        ];
        $proxy = httpProxy();
        if ($proxy) {
            $context["curl"] = ["proxy" => $proxy];
        }
        $cert = stream_context_get_params(
            stream_socket_client(
                "ssl://$hostname:$port",
                $errno, $errstr, 30, STREAM_CLIENT_CONNECT,
                stream_context_create($context)
            )
        );
        if (!$cert) {
            return [];
        }
        $certinfo = openssl_x509_parse($cert["options"]["ssl"]["peer_certificate"]);
        return [
            date("Y-m-d H:i:s", $certinfo["validFrom_time_t"]),
            date("Y-m-d H:i:s", $certinfo["validTo_time_t"]),
        ];
    }

    public function add(string $hostname, int $port): int {
        [$notBefore, $notAfter] = $this->lookup($hostname, $port);
        if (is_null($notBefore) || is_null($notAfter)) {
            return 0;
        }
        return $this->pg->insert("
            INSERT INTO ssl_host
                   (hostname, port, not_before, not_after)
            VALUES (?,        ?,    ?,          ?)
            ", $hostname, $port, $notBefore, $notAfter
        );
    }

    public function removeList(array $idList): int {
        if (count($idList) == 0) {
            return 0;
        }
        return $this->pg->prepared_query("
            DELETE FROM ssl_host WHERE id_ssl_host in (" . placeholders($idList) . ")
            ", ...$idList
        );
    }

    public function list(): array {
        return $this->pg->all("
            SELECT id_ssl_host AS id,
                hostname,
                port,
                not_before,
                not_after,
                created
            FROM ssl_host
            ORDER BY not_after, hostname
        ");
    }

    public function expirySoon(string $interval): bool {
        return (bool)$this->pg->scalar("
            select 1 where exists (
                select 1 from ssl_host where not_after < now() + ?::interval
            )
            ", $interval
        );
    }

    public function schedule(): int {
        $update = 0;
        $st = $this->pg->prepare("
            UPDATE ssl_host SET
                not_before = ?,
                not_after = ?
            WHERE (not_before < ? OR not_after < ?)
                AND id_ssl_host = ?
        ");
        foreach ($this->list() as $host) {
            [$notBefore, $notAfter] = $this->lookup($host['hostname'], $host['port']);
            $st->execute([$notBefore, $notAfter, $notBefore, $notAfter, $host['id']]);
            $update += $st->rowCount();
        }
        return $update;
    }
}
