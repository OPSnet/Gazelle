<?php

namespace Gazelle\User;

class PermissionRateLimit extends \Gazelle\BaseUser {
    final const tableName = 'permission_rate_limit';

    public function flush(): PermissionRateLimit { $this->user()->flush(); return $this; }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }

    public function metrics(): ?array {
         return self::$db->rowAssoc("
            SELECT factor, overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ", $this->user->id()
        );
    }

    public function safeFactor(): bool {
         $classFactor = self::$db->scalar("
            SELECT factor
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ", $this->user->id()
        );
        if (is_null($classFactor)) {
            return true;
        }
        return $this->activityFactor() <= $classFactor;
    }

    public function safeOvershoot(): bool {
         $classOvershoot = self::$db->scalar("
            SELECT overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ", $this->user->id()
        );
        if (is_null($classOvershoot)) {
            return true;
        }
        return $this->recentDownloadCount() <= $classOvershoot;
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

    public function recentDownloadCount(): int {
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
