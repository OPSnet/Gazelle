<?php

namespace Gazelle\Manager;

class Session extends \Gazelle\Base {

    public function purge(): int {
        self::$db->prepared_query("
            SELECT concat('users_sessions_', UserID) as ck
            FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");
        $cacheKeys = self::$db->collect('ck', false);
        if (!$cacheKeys) {
            return 0;
        }

        self::$db->prepared_query("
            DELETE FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");
        self::$cache->deleteMulti($cacheKeys);
        return count($cacheKeys);
    }
}
