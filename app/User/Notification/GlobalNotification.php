<?php

namespace Gazelle\User\Notification;

// because 'Global' is a reserved word :-(

class GlobalNotification extends AbstractNotification {
    protected const CLEARED = 'u_clear_global_%d';

    protected string $className;

    public function className(): string {
        return $this->className ?? 'error';
    }

    public function clear(): int {
        self::$cache->cache_value(
            sprintf(self::CLEARED, $this->user->id()),
            true,
            (new \Gazelle\Notification\GlobalNotification)->remaining()
        );
        return 1;
    }

    public function load(): bool {
        $alert = (new \Gazelle\Notification\GlobalNotification)->alert();
        if ($alert && self::$cache->get_value(sprintf(self::CLEARED, $this->user->id())) === false) {
            $this->title     = $alert['title'];
            $this->url       = $alert['url'];
            $this->className = $alert['level'];
            return true;
        }
        return false;
    }
}
