<?php

namespace Gazelle\Manager;

class ErrorLog extends \Gazelle\Base {

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

    public function total(): int {
        return self::$db->scalar("
            SELECT count(*) FROM error_log
        ");
    }

    public function list(int $limit, int $offset): array {
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
                error_list
            FROM error_log
            ORDER BY updated DESC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $result = self::$db->to_array('error_log_id', MYSQLI_ASSOC, false);
        $list = [];
        foreach ($result as $item) {
            $item['trace'] = explode("\n", $item['trace']);
            $item['request'] = json_decode($item['request'], true);
            $item['error_list'] = json_decode($item['error_list'], true);
            $list[] = $item;
        }
        return $list;
    }
}
