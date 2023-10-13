<?php

namespace Gazelle\User\Notification;

class Inbox extends AbstractNotification {
    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = '0'
            WHERE Unread = '1'
                AND UserID = ?
            ", $this->user->id()
        );
        self::$cache->delete_value('inbox_new_' . $this->user->id());
        return self::$db->affected_rows();
    }

    public function load(): bool {
        $total = $this->user->inboxUnreadCount();
        if ($total) {
            $this->title = 'You have ' . article($total) . ' new message' . plural($total);
            $this->url   = "inbox.php";
            return true;
        }
        return false;
    }
}
