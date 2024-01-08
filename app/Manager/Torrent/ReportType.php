<?php

namespace Gazelle\Manager\Torrent;

class ReportType extends \Gazelle\Base {
    final public const ID_KEY   = 'zz_trt_%d';
    final public const NAME_KEY = 'zz_trtn_%s';
    final public const TYPE_KEY = 'zz_trtt_%s';

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
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Torrent\ReportType($id) : null;
    }

    public function findByName(string $name): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::NAME_KEY, $name);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT torrent_report_configuration_id
                FROM torrent_report_configuration
                WHERE name = ?
                ", $name
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? $this->findById($id) : null;
    }

    public function findByType(string $type): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::TYPE_KEY, $type);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT torrent_report_configuration_id
                FROM torrent_report_configuration
                WHERE type = ?
                ", $type
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? $this->findById($id) : null;
    }

    public function list(): array {
        self::$db->prepared_query("
            SELECT torrent_report_configuration_id
            FROM torrent_report_configuration
            ORDER BY sequence, category_id
        ");
        return array_map(fn ($id) => $this->findById($id), self::$db->collect(0, false));
    }

    public function categoryList(int $categoryId): array {
        self::$db->prepared_query("
            SELECT torrent_report_configuration_id
            FROM torrent_report_configuration
            WHERE category_id IN (0, ?)
            ORDER BY sequence
            ", $categoryId
        );
        return array_map(fn ($id) => $this->findById($id), self::$db->collect(0, false));
    }
}
