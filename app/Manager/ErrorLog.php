<?php

namespace Gazelle\Manager;

use Gazelle\BaseManager;

class ErrorLog extends BaseManager {
    protected string $filter;

    protected function digest(string $trace, array $errorList): string {
        return hash('sha256', $trace . json_encode($errorList));
    }

    public function create(
        string $uri,
        ?int $userId,
        float $duration,
        int $memory,
        int $nrQuery,
        int $nrCache,
        string $trace,
        array $request,
        array $errorList,
    ): \Gazelle\ErrorLog {
        $len = strlen($trace);
        if ($len > 4000) {
            $clipped = $len - 4000;
            $trace = substr($trace, 0, 4000) . "[...$clipped chars clipped]";
        }
        $id = $this->pg()->scalarex("
            merge into error_log using (
                select $1 as uri,
                    $2::int as id_user,
                    $3::double precision as duration,
                    $4::bigint as memory,
                    $5::int as nr_query,
                    $6::int as nr_cache,
                    $7 as trace,
                    $8::jsonb as request,
                    $9::jsonb as error_list,
                    $10::bytea as digest
                ) as i on i.digest = error_log.digest
            when not matched then
                insert (  uri,   id_user,   duration,   memory,   nr_query,   nr_cache,   trace,   request,   error_list,   digest)
                values (i.uri, i.id_user, i.duration, i.memory, i.nr_query, i.nr_cache, i.trace, i.request, i.error_list, i.digest)
            when matched then
                update set
                    seen = error_log.seen + 1,
                    updated = now()
            returning id_error_log;
            ", substr($uri, 0, 255), $userId, $duration, $memory, $nrQuery, $nrCache, // 6
                $trace, json_encode($request), json_encode($errorList), // 9
                $this->digest($trace, $errorList), // 10
        );
        return new \Gazelle\ErrorLog($id);
    }

    /**
     * Get an error log based on its ID
     */
    public function findById(int $id): ?\Gazelle\ErrorLog {
        $errorId = (int)$this->pg()->scalarex("
            select id_error_log from error_log where id_error_log = $1
            ", $id
        );
        return $errorId ? new \Gazelle\ErrorLog($errorId) : null;
    }

    /**
     * Get an error log based on its digest
     */
    public function findByDigest(string $trace, array $errorList): ?\Gazelle\ErrorLog {
        $id = (int)$this->pg()->scalarex("
            select id_error_log from error_log where digest = $1
            ", $this->digest($trace, $errorList),
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function findByPrev(int $errorId): ?\Gazelle\ErrorLog {
        $id = (int)$this->pg()->scalarex("
            select id_error_log
            from error_log
            where updated > (select updated from error_log where id_error_log = $1)
            order by updated asc
            limit 1
            ", $errorId
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function findByNext(int $errorId): ?\Gazelle\ErrorLog {
        $id = (int)$this->pg()->scalarex("
            select id_error_log
            from error_log
            where updated < (select updated from error_log where id_error_log = $1)
            order by updated desc
            limit 1
            ", $errorId
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function setFilter(string $filter): static {
        $this->filter = $filter;
        return $this;
    }

    public function total(): int {
        $args = [];
        if (!isset($this->filter)) {
            $where = '';
        } else {
            $where = "where uri ~* $1";
            $args[] = $this->filter;
        }
        return (int)$this->pg()->scalarex("
            select count(*) from error_log $where
            ", ...$args
        );
    }

    public function list(string $orderBy, string $dir, int $limit, int $offset): array {
        $args = [];
        if (!isset($this->filter)) {
            $where = '';
        } else {
            $where = "where uri ~ ?";
            $args[] = $this->filter;
        }
        array_push($args, $limit, $offset);
        /* In theory this could simply fetch the ids and then hydrate the
         * individual ErrorLog objects, but as they are uncached, it would
         * require n+1 queries overall.
         */
        $result = $this->pg()->all("
            select id_error_log as id,
                duration,
                memory,
                nr_cache,
                nr_query,
                seen,
                created,
                updated,
                uri,
                trace,
                request,
                error_list,
                logged_var
            from error_log $where
            order by {$orderBy} {$dir}
            limit ? offset ?
            ", ...$args
        );
        $list = [];
        foreach ($result as $item) {
            $item['trace']      = explode("\n", $item['trace']);
            $item['request']    = json_decode($item['request'], true);
            $item['error_list'] = json_decode($item['error_list'], true);
            $item['logged_var'] = json_decode($item['logged_var'], true);
            $list[] = $item;
        }
        return $list;
    }

    public function removeList(array $list): int {
        return $this->pg()->prepared_query("
            delete from error_log where id_error_log in (
            " . placeholders($list) . ")", ...$list
        );
    }

    public function removeSlow(float $duration): int {
        return $this->pg()->prepared_query("
            delete from error_log where duration >= ?
            ", $duration
        );
    }
}
