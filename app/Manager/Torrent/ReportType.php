<?php

namespace Gazelle\Manager\Torrent;

class ReportType extends \Gazelle\Base {
    const ID_KEY = 'zz_trt_%d';

    public function findById(int $reportTypeId): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::ID_KEY, $reportTypeId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT torrent_report_configuration_id
                FROM torrent_report_configuration
                WHERE torrent_report_configuration_id = ?
                ", $reportTypeId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Torrent\ReportType($id) : null;
    }

    public function list(): array {
        self::$db->prepared_query("
            SELECT torrent_report_configuration_id FROM torrent_report_configuration ORDER BY category_id, sequence
        ");
        return array_map(fn ($id) => $this->findById($id), self::$db->collect(0, false));
    }
}
