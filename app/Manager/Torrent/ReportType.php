<?php

namespace Gazelle\Manager\Torrent;

class ReportType extends \Gazelle\Base {
    final public const ID_KEY   = 'zz_trt_%d';
    final public const NAME_KEY = 'zz_trtn_%s';
    final public const TYPE_KEY = 'zz_trtt_%s';

    public function findById(int $id): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::ID_KEY, $id);
        $reportTypeId = self::$cache->get_value($key);
        if ($reportTypeId === false) {
            $reportTypeId = $this->pg()->scalarex("
                select id_torrent_report_configuration
                from torrent_report_configuration
                where id_torrent_report_configuration = $1
                ", $id
            );
            if (!is_null($reportTypeId)) {
                self::$cache->cache_value($key, $reportTypeId, 0);
            }
        }
        return is_null($reportTypeId) ? null : new \Gazelle\Torrent\ReportType((int)$reportTypeId);
    }

    public function findByName(string $name): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::NAME_KEY, $name);
        $reportTypeId = self::$cache->get_value($key);
        if ($reportTypeId === false) {
            $reportTypeId = $this->pg()->scalarex("
                select id_torrent_report_configuration
                from torrent_report_configuration
                where name = $1
                ", $name
            );
            if (!is_null($reportTypeId)) {
                self::$cache->cache_value($key, $reportTypeId, 0);
            }
        }
        return is_null($reportTypeId) ? null : $this->findById((int)$reportTypeId);
    }

    public function findByType(string $type): ?\Gazelle\Torrent\ReportType {
        $key = sprintf(self::TYPE_KEY, $type);
        $reportTypeId = self::$cache->get_value($key);
        if ($reportTypeId === false) {
            $reportTypeId = $this->pg()->scalarex("
                select id_torrent_report_configuration
                from torrent_report_configuration
                where type = $1
                ", $type
            );
            if (!is_null($reportTypeId)) {
                self::$cache->cache_value($key, $reportTypeId, 0);
            }
        }
        return is_null($reportTypeId) ? null : $this->findById((int)$reportTypeId);
    }

    public function list(): array {
        return array_map(
            fn ($id) => $this->findById($id),
            $this->pg()->columnex("
                select id_torrent_report_configuration
                from torrent_report_configuration
                order by sequence, id_category
            ")
        );
    }

    public function categoryList(int $categoryId): array {
        return array_map(
            fn ($id) => $this->findById($id),
            $this->pg()->columnex("
                select id_torrent_report_configuration
                from torrent_report_configuration
                where id_category IN (0, $1)
                order by sequence
                ", $categoryId
            )
        );
    }
}
