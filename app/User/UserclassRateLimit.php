<?php

namespace Gazelle\User;

class UserclassRateLimit extends \Gazelle\BaseUser {
    final public const tableName = 'permission_rate_limit';

    protected array $info;

    public function flush(): static {
        $this->user()->flush();
        unset($this->info);
        return $this;
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

    public function userclassFactor(): ?float {
        return $this->info()['factor'] ?? null;
    }

    public function userclassOvershoot(): ?int {
        return $this->info()['overshoot'] ?? null;
    }

    public function userFactor(): float {
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

    public function hasExceededFactor(): bool {
        $userclassFactor = $this->userclassFactor();
        $userFactor      = $this->userFactor();
        if (is_null($userclassFactor) || is_nan($userFactor)) {
            return false;
        }
        return $userFactor > $userclassFactor;
    }

    public function hasExceededTotal(): bool {
        $userclassOvershoot = $this->userclassOvershoot();
        if (is_null($userclassOvershoot)) {
            return false;
        }
        return $this->recentDownloadTotal() > $userclassOvershoot;
    }

    public function isOvershoot(?\Gazelle\Torrent $torrent = null): bool {
        if ($this->hasExceededFactor() && $this->hasExceededTotal()) {
            if ($torrent) {
                $this->register($torrent);
            }
            return true;
        }
        return false;
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
}
