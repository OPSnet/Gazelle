<?php

namespace Gazelle\Manager;

use Gazelle\Util\Curl;

class SSLHost extends \Gazelle\Base {
    protected Curl $curl;

    public function __construct(?Curl $curl = null) {
        // for testing
        if ($curl !== null) {
            $this->curl = $curl;
        } else {
            $this->curl = new Curl()
                ->setOption(CURLOPT_SSL_VERIFYPEER, true)
                ->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        }
        // prevent connection+session reuse, no cert info will be available
        $this->curl
            ->setOption(CURLOPT_CERTINFO, true)
            ->setOption(CURLOPT_FRESH_CONNECT, true)
            ->setOption(CURLOPT_FORBID_REUSE, true)
            ->setOption(CURLOPT_SSL_SESSIONID_CACHE, 0);
    }

    public function lookup(string $hostname, int $port): array {
        if (!preg_match('/^(?:[\w-]+)(?:\.[\w-]+)*$/', $hostname)) {
            throw new \Exception("malformed hostname");
        }
        if ($port <= 0 || $port > 0xffff) {
            throw new \Exception("malformed port");
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

        $url = "https://{$hostname}:{$port}";
        $this->curl->fetch($url);
        $info = $this->curl->curlInfo(CURLINFO_CERTINFO);

        if (!isset($info[0]['Start date'], $info[0]['Expire date'])) {
            // some network error
            return [];
        }

        try {
            $notBefore = new \DateTimeImmutable($info[0]['Start date']);
            $notAfter = new \DateTimeImmutable($info[0]['Expire date']);
        } catch (\DateMalformedStringException) {
            throw new \Exception("curl returned malformed date");
        }

        return [$notBefore, $notAfter];
    }

    /**
     * Return int id on success, null otherwise
     */
    public function add(string $hostname, int $port): ?int {
        [$notBefore, $notAfter] = $this->lookup($hostname, $port);
        if (is_null($notBefore) || is_null($notAfter)) {
            return null;
        }
        return $this->pg()->scalarex("
            insert into ssl_host
                   (hostname, port, not_before, not_after)
            values ($1,       $2,   $3,         $4)
            returning id_ssl_host
            ", $hostname, $port, $notBefore, $notAfter
        );
    }

    public function removeList(array $idList): int {
        if (count($idList) === 0) {
            return 0;
        }
        return $this->pg()->query("
            delete from ssl_host where id_ssl_host = any($1::int[])
            ", $this->pg()->integerConverter()->output($idList)
        );
    }

    public function list(): array {
        return $this->pg()->allex("
            select id_ssl_host as id,
                hostname,
                port,
                not_before,
                not_after,
                created
            from ssl_host
            order by not_after, hostname
        ");
    }

    /**
     * Return the number of full days before the next SSL certificate expiry.
     * Can return negative numbers.
     * Returns null if no domains are tracked.
     */
    public function expiryDays(): ?int {
        return $this->pg()->scalarex("
            select extract(day from not_after - now())
            from ssl_host
            order by not_after asc
            limit 1
        ");
    }

    public function schedule(): int {
        $args = [];
        foreach ($this->list() as $host) {
            [$notBefore, $notAfter] = $this->lookup($host['hostname'], $host['port']);
            if ($notBefore !== null && $notAfter !== null) {
                array_push($args, $host['id'], $notBefore, $notAfter);
            }
        }
        if (!$args) {
            return 0;
        }
        return $this->pg()->query("
            update ssl_host set
                not_before = c.not_before::timestamptz,
                not_after = c.not_after::timestamptz
            from (values " .
                placeholders_pg(array_slice($args, 0, (int)(count($args) / 3)), '(%s, %s, %s)', 3)
            . ") as c(id_ssl_host, not_before, not_after) 
            where c.id_ssl_host::int = ssl_host.id_ssl_host
        ", ...$args);
    }
}
