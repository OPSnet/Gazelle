<?php

namespace Gazelle\Manager;

class ErrorLog extends \Gazelle\BaseManager {

    protected string $filter;

    public function create(
       string $uri, int $userId, float $duration, int $memory, int $nrQuery, int $nrCache,
       string $digest, string $trace, string $request, string $errorList, string $loggedVar
    ): int {
        self::$db->begin_transaction();
        $id = self::$db->scalar("
            SELECT error_log_id FROM error_log WHERE digest = ?
            ", $digest
        );
        if ($id) {
            self::$db->prepared_query("
                UPDATE error_log SET
                    updated = now(),
                    seen = seen + 1
                WHERE error_log_id = ?
                ", $id
            );
        } else {
            self::$db->prepared_query("
                INSERT INTO error_log
                       (uri, user_id, duration, memory, nr_query, nr_cache, digest, trace, request, error_list, logged_var)
                VALUES (?,   ?,       ?,        ?,      ?,        ?,        ?,      ?,     ?,       ?,          ?)
                ", $uri, $userId, $duration, $memory, $nrQuery, $nrCache, $digest, $trace, $request, $errorList, $loggedVar
            );
            $id = self::$db->inserted_id();
        }
        self::$db->commit();
        return $id;
    }

    /**
     * Get an eror log based on its ID
     */
    public function findById(int $errorId): ?\Gazelle\ErrorLog {
        $id = self::$db->scalar("
            SELECT error_log_id FROM error_log WHERE error_log_id = ?
            ", $errorId
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function findByPrev(int $errorId): ?\Gazelle\ErrorLog {
        $id = self::$db->scalar("
            SELECT error_log_id
            FROM error_log
            WHERE updated > (SELECT updated FROM error_log WHERE error_log_id = ?)
            ORDER BY updated ASC
            LIMIT 1
            ", $errorId
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function findByNext(int $errorId): ?\Gazelle\ErrorLog {
        $id = self::$db->scalar("
            SELECT error_log_id
            FROM error_log
            WHERE updated < (SELECT updated FROM error_log WHERE error_log_id = ?)
            ORDER BY updated DESC
            LIMIT 1
            ", $errorId
        );
        return $id ? new \Gazelle\ErrorLog($id) : null;
    }

    public function setFilter(string $filter) {
        $this->filter = $filter;
        return $this;
    }

    public function total(): int {
        $args = [];
        if (!isset($this->filter)) {
            $where = '';
        } else {
            $where = "WHERE uri LIKE concat('%', ?, '%')";
            $args[] = $this->filter;
        }
        return self::$db->scalar("
            SELECT count(*) FROM error_log $where
            ", ...$args
        );
    }

    public function list(string $orderBy, string $dir, int $limit, int $offset): array {
        $args = [];
        if (!isset($this->filter)) {
            $where = '';
        } else {
            $where = "WHERE uri LIKE concat('%', ?, '%')";
            $args[] = $this->filter;
        }
        array_push($args, $limit, $offset);
        self::$db->prepared_query("
            SELECT error_log_id,
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
            FROM error_log $where
            ORDER BY {$orderBy} {$dir}
            LIMIT ? OFFSET ?
            ", ...$args
        );
        $result = self::$db->to_array('error_log_id', MYSQLI_ASSOC, false);
        $list = [];
        foreach ($result as $item) {
            $item['trace'] = explode("\n", $item['trace']);
            $item['request'] = json_decode($item['request'], true);
            $item['error_list'] = json_decode($item['error_list'], true);
            $item['logged_var'] = json_decode($item['logged_var'], true);
            $list[] = $item;
        }
        return $list;
    }

    public function remove(array $list): int {
        self::$db->prepared_query("
            DELETE FROM error_log WHERE error_log_id IN (
            " . placeholders($list) . ")", ...$list
        );
        return self::$db->affected_rows();
    }

    public function removeSlow(float $duration): int {
        self::$db->prepared_query("
            DELETE FROM error_log WHERE duration >= ?
            ", $duration
        );
        return self::$db->affected_rows();
    }
}
