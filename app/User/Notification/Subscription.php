<?php

namespace Gazelle\User\Notification;

class Subscription extends AbstractNotification {
    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        return (new \Gazelle\User\Subscription($this->user))->clear();
    }

    public function load(): bool {
        $total = (new \Gazelle\User\Subscription($this->user))->unread();
        if ($total > 0) {
            $this->title = 'New subscription' . plural($total);
            $this->url   = 'userhistory.php?action=subscriptions';
            return true;
        }
        return false;
    }
}
