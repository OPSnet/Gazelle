<?php

namespace Gazelle\Manager;

use \Gazelle\Enum\UserStatus;

class Stylesheet extends \Gazelle\Base {
    final const CACHE_KEY = 'csslist';

    protected array $info;

    public function list(): array {
        if (!isset($this->info)) {
            $info = self::$cache->get_value(self::CACHE_KEY);
            if ($info === false) {
                self::$db->prepared_query("
                    SELECT ID AS id,
                        lower(replace(Name, ' ', '_')) AS css_name,
                        Name AS name,
                        theme
                    FROM stylesheets
                    ORDER BY ID ASC
                ");
                $info = self::$db->to_array(false, MYSQLI_ASSOC, false);
                self::$cache->cache_value(self::CACHE_KEY, $info, 0);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function usageList(string $orderBy, string $direction): array {
        self::$db->prepared_query("
            SELECT s.ID                       AS id,
                s.Name                        AS name,
                s.Description                 AS description,
                s.Default                     AS initial,
                s.theme                       AS theme,
                count(um.ID)                  AS total,
                sum(if(um.Enabled = ?, 1, 0)) AS total_enabled
            FROM stylesheets s
            LEFT JOIN users_main um ON (um.stylesheet_id = s.ID)
            GROUP BY s.ID, s.Name, s.Description, s.theme
            ORDER BY $orderBy $direction
            ", UserStatus::enabled->value
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
