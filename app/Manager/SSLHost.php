<?php

namespace Gazelle\Manager;

class SSLHost extends \Gazelle\Base {
    use \Gazelle\Pg;

    public function lookup(string $hostname, int $port): array {

        if (!preg_match('/^(?:[\w-]+)(?:\.[\w-]+)+$/', $hostname)) {
            return [];
        }
        if ($port <= 0) {
            return [];
        }
        $notBefore = null;
        $notAfter = null;
        $output = explode("\n", trim((string)shell_exec(SERVER_ROOT . "/scripts/ssl-check $hostname $port")));
        if (count($output) != 2) {
            return [];
        }
        foreach ($output as $line) {
            [$event, $date] = explode('=', $line);
            $date = date('Y-m-d H:m:s', (int)strtotime((string)$date));
            switch ($event) {
                case 'notAfter':
                    $notAfter = $date;
                    break;
                case 'notBefore':
                    $notBefore = $date;
                    break;
                default:
                    break;
            }
        }
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
