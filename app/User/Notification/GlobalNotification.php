<?php

namespace Gazelle\User\Notification;

// because 'Global' is a reserved word :-(

class GlobalNotification extends AbstractNotification {
    protected const CLEARED = 'u_clear_global_%d';

    protected string $className;

    public function className(): string {
        return isset($this->className) ? $this->className : 'error';
    }

    public function clear(): int {
        $global = self::$cache->get_value('global_notification');
        if ($global) {
            // This is some trickery
            // since we can't know which users have the read cache key set
            // we set the expiration time of their cache key to that of the length of the notification
            // this guarantees that their cache key will expire after the notification expires
            self::$cache->cache_value(sprintf(self::CLEARED, $this->user->id()), true, $global['Expiration']);
            return 1;
        }
        return 0;
    }

    public function load(): bool {
        $notification = self::$cache->get_value('global_notification');
        if ($notification !== false && self::$cache->get_value(sprintf(self::CLEARED, $this->user->id()) !== false)) {
            $this->title     = $notification['Message'];
            $this->url       = $notification['URL'];
            $this->className = $notification['Importance'];
            return true;
        }
        return false;
    }
}
