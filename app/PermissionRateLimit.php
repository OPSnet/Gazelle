<?php

namespace Gazelle;

class PermissionRateLimit extends BaseUser {

    public function metrics(): ?array {
         return self::$db->rowAssoc('
            SELECT factor, overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $this->user->id()
        );
    }

    public function safeFactor(): bool {
         $classFactor = self::$db->scalar('
            SELECT factor
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $this->user->id()
        );
        if (is_null($classFactor)) {
            return true;
        }
        return $this->user->downloadSnatchFactor() <= $classFactor;
    }

    public function safeOvershoot(): bool {
         $classOvershoot = self::$db->scalar('
            SELECT overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $this->user->id()
        );
        if (is_null($classOvershoot)) {
            return true;
        }
        return $this->user->torrentRecentDownloadCount() <= $classOvershoot;
    }
}
