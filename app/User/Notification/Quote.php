<?php

namespace Gazelle\User\Notification;

class Quote extends AbstractNotification {

    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        self::$db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = '0'
            WHERE UserID = ?
            ", $this->user->id()
        );
        self::$cache->delete_value('user_quote_unread_' . $this->user->id());
        return self::$db->affected_rows();
    }

    public function load(): bool {
        if ($this->user->notifyOnQuote()) {
            $subscription = new \Gazelle\Subscription($this->user);
            $total = $subscription->unreadQuotes();
            if ($total > 0) {
                $this->title = 'New quote' . plural($total);
                $this->url   = 'userhistory.php?action=quote_notifications';
                return true;
            }
        }
        return false;
    }
}
