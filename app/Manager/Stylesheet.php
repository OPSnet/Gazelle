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
            SELECT s.ID             AS id,
                s.Name              AS name,
                s.Description       AS description,
                s.Default           AS initial,
                s.theme,
                ifnull(ui.total, 0) AS total_enabled,
                ifnull(ud.total, 0) AS total
            FROM stylesheets AS s
            LEFT JOIN (
                SELECT StyleID,
                    count(*) AS total
                FROM users_info AS ui
                INNER JOIN users_main AS um ON (ui.UserID = um.ID)
                WHERE um.Enabled = ?
                GROUP BY StyleID
            ) AS ui ON (s.ID = ui.StyleID)
            LEFT JOIN (
                SELECT StyleID,
                    count(*) AS total
                FROM users_info AS ui
                INNER JOIN users_main AS um ON (ui.UserID = um.ID)
                GROUP BY StyleID
            ) AS ud ON (s.ID = ud.StyleID)
            ORDER BY $orderBy $direction
            ", UserStatus::enabled->value
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
