<?php

namespace Gazelle\Manager;

class SSLHost extends \Gazelle\Base {
    use \Gazelle\Pg;

    public function lookup(string $hostname, int $port): array {
        if (!preg_match('/^(?:[\w-]+)(?:\.[\w-]+)+$/', $hostname)) {
            return [];
        }
        if ($port <= 0 || $port > 0xffff) {
            return [];
        }

        // The following does not work:
        // $cx = stream_context_create([
        //      "ssl"  => [ "capture_peer_cert" => TRUE],
        //      "http" => [ "proxy" => "tcp://proxy:3128", "request_fulluri" => true]
        //  ]);
        // $stream = stream_socket_client("ssl://some.where:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $cx);
        // echo $errstr;
        // → 'No route to host'
        //
        // That is, you cannot proxy a socket connection over Squid.
        // Let us know if you can show otherwise.

        $url  = "https://{$hostname}:{$port}";
        $curl = (new \Gazelle\Util\Curl())
            ->setOption(CURLOPT_CERTINFO, true)
            ->setOption(CURLOPT_SSL_VERIFYPEER, true)
            ->setOption(CURLOPT_SSL_VERIFYHOST, 2);

        $curl->fetch($url);
        $info = $curl->curlInfo(CURLINFO_CERTINFO);

        if (!isset($info[0]['Start date'], $info[0]['Expire date'])) {
            return [];
        }

        $notBefore = date('Y-m-d H:m:s', (int)strtotime($info[0]['Start date']));
        $notAfter  = date('Y-m-d H:m:s', (int)strtotime($info[0]['Expire date']));

        return [$notBefore, $notAfter];
    }

    public function add(string $hostname, int $port): int {
        [$notBefore, $notAfter] = $this->lookup($hostname, $port);
        if (is_null($notBefore) || is_null($notAfter)) {
            return 0;
        }
        return $this->pg()->insert("
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
        return $this->pg()->prepared_query("
            DELETE FROM ssl_host WHERE id_ssl_host in (" . placeholders($idList) . ")
            ", ...$idList
        );
    }

    public function list(): array {
        return $this->pg()->all("
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
        return (bool)$this->pg()->scalar("
            select 1 where exists (
                select 1 from ssl_host where not_after < now() + ?::interval
            )
            ", $interval
        );
    }

    public function schedule(): int {
        $update = 0;
        $st = $this->pg()->prepare("
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
