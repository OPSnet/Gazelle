<?php

declare(strict_types=1);

namespace Gazelle\Util;

use Gazelle\User;

/**
 * track recently disabled users in memcached
 */
class DisabledUserHistory extends \Gazelle\Base {
    protected const string CACHE_KEY = 'recently_disabled_users';
    public const int MAX_ENTRIES = 50;

    public static function add(User $user, string $reason): void {
        $list = static::$cache->get_value(static::CACHE_KEY) ?: [];
        $list[] = [$user->id, $reason, time()];
        $list = array_slice($list, -static::MAX_ENTRIES);
        static::$cache->cache_value(static::CACHE_KEY, $list, 3 * 86400);
    }

    /**
     * @return array [[user_id, disable_reason, timestamp],...]
     */
    public static function get(): array {
        return static::$cache->get_value(static::CACHE_KEY) ?: [];
    }
}
