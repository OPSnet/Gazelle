<?php

namespace Gazelle\Manager;

class Category extends \Gazelle\Base {
    const NAME_KEY   = 'cat_name';
    const REPORT_KEY = 'cat_report';

    protected array $info = [];

    public function nameList(): array {
        if (!isset($this->info['name'])) {
            $list = self::$cache->get_value(self::NAME_KEY);
            if ($list === false) {
                self::$db->prepared_query("
                    SELECT category_id AS id,
                        name
                    FROM category
                    WHERE is_system IS false
                    ORDER BY category_id
                ");
                $list = self::$db->to_array('id', MYSQLI_ASSOC, false);
                self::$cache->cache_value(self::NAME_KEY, $list, 0);
            }
            $this->info['name'] = $list;
        }
        return $this->info['name'];
    }

    /**
     * Category names for reports.
     * There is an extra "Global" category for reports that apply to all upload categories.
     */
    public function categoryList(): array {
        if (!isset($this->info['report'])) {
            $list = self::$cache->get_value(self::REPORT_KEY);
            if ($list === false) {
                self::$db->prepared_query("
                    SELECT category_id AS id,
                        name
                    FROM category
                    ORDER BY category_id
                ");
                $list = self::$db->to_array('id', MYSQLI_ASSOC, false);
                self::$cache->cache_value(self::REPORT_KEY, $list, 0);
            }
            $this->info['report'] = $list;
        }
        return $this->info['report'];
    }
}
