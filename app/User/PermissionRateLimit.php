<?php

namespace Gazelle\User;

class PermissionRateLimit extends \Gazelle\BaseUser {
    final const tableName = 'permission_rate_limit';

    protected array|null $info;

    public function flush(): static {
        $this->user()->flush();
        unset($this->info);
        return $this;
    }

    public function register(\Gazelle\Torrent $torrent): int {
        self::$db->prepared_query("
            INSERT INTO ratelimit_torrent
                   (user_id, torrent_id)
            VALUES (?,       ?)
            ", $this->id(), $torrent->id()
        );
        $id = self::$db->inserted_id();
        $key = "user_429_flood_{$this->id()}";
        if (self::$cache->get_value($key)) {
            self::$cache->increment($key);
        } else {
            self::$cache->cache_value($key, 1, 3600);
        }
        return $id;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = self::$db->rowAssoc("
            SELECT factor, overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ", $this->user->id()
        ) ?? [
            'factor'    => null,
            'overshoot' => null,
        ];
        return $this->info;
    }

    public function safeFactor(): bool {
        $factor = $this->info()['factor'];
        if (is_null($factor)) {
            return true;
        }
        return $this->activityFactor() <= $factor;
    }

    public function safeOvershoot(): bool {
        $overshoot = $this->info()['overshoot'];
        if (is_null($overshoot)) {
            return true;
        }
        return $this->recentDownloadTotal() <= $overshoot;
    }

    public function activityFactor(): float {
        if ($this->user->hasAttr('unlimited-download')) {
            // they are whitelisted, let them through
            return 0.0;
        }
        $stats = $this->user->stats();
        $activity = max($stats->snatchUnique(), $stats->seedingTotal());
        return $activity ? $stats->downloadUnique() / $activity : NAN;
    }

    public function recentDownloadTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.Time > now() - INTERVAL 1 DAY
                AND t.UserID != ud.UserID
                AND ud.UserID = ?
            ", $this->user->id()
        );
    }
}
