<?php

namespace Gazelle;

class API extends Base {
    public function validateToken(int $appId, string $token): bool {
        $key = "api_applications_{$appId}";
        $app = self::$cache->get_value($key);
        if ($app === false) {
            $app = self::$db->rowAssoc("
                SELECT Token, Name
                FROM api_applications
                WHERE ID = ?
                LIMIT 1
                ", $appId
            );
            if (is_null($app)) {
                return false;
            }
            self::$cache->cache_value($key, $app, 0);
        }
        return $app['Token'] === $token;
    }
}
