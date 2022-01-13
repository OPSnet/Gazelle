<?php

namespace Gazelle\Notification;

class GlobalNotification extends \Gazelle\Base {

    protected const CACHE_KEY = 'global_notification';

    /**
     * What levels are available? (Principal use is for CSS markup).
     */
    public function level(): array {
        return [
            'confirmation',
            'information',
            'warning',
            'error',
        ];
    }

    public function create(string $title, string $url, string $level, int $expiry): bool {
        if (empty($title) || $expiry < 1) {
            return false;
        }
        self::$cache->cache_value(self::CACHE_KEY, [
                'title'   => $title,
                'url'     => $url,
                'level'   => $level,
                'expiry'  => $expiry,
                'created' => time(),
            ],
            $expiry * 60
        );
        return true;
    }

    public function alert(): ?array {
        $alert = self::$cache->get_value(self::CACHE_KEY);
        return $alert === false ? null : $alert;
    }

    /**
     * How much time (in seconds) remains on this alert before expiry?
     * Used to set a cache key to mark it as read per user. Always return at
     * least 1 second, so that a cache value is set and then expired, since
     * a value of 0 means "cache forever".
     */
    public function remaining(): int {
        $alert = $this->alert();
        return is_null($alert) ? 1 : ($alert['created'] + $alert['expiry'] * 60) - time();
    }

    public function remove() {
        self::$cache->delete_value(self::CACHE_KEY);
    }
}
