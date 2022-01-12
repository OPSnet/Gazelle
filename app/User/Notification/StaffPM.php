<?php

namespace Gazelle\User\Notification;

class StaffPM extends AbstractNotification {

    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = 0
            WHERE Unread = 1
                AND UserID = ?
            ", $this->user->id()
        );
        self::$cache->delete_value('staff_pm_new_' . $this->user->id());
        return self::$db->affected_rows();
    }

    public function load(): bool {
        $total = self::$cache->get_value('staff_pm_new_' . $this->user->id());
        if ($total === false) {
            $total = self::$db->scalar("
                SELECT count(*) FROM staff_pm_conversations WHERE Unread = 1 AND UserID = ?
                ", $this->user->id()
            );
            self::$cache->cache_value('staff_pm_new_' . $this->user->id(), $total, 0);
        }
        if ($total > 0) {
            $this->title = 'You have ' . article($total) . ' new Staff PM' . plural($total);
            $this->url   = 'staffpm.php';
            return true;
        }
        return false;
    }
}
