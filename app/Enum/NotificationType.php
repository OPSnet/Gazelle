<?php

namespace Gazelle\Enum;

// each entry in this enum also requires a lowercase row in the user_attr table
// write a postgres migration when adding a new notification type
enum NotificationType: string {
    case NEWS         = 'News';
    case BLOG         = 'Blog';
    case INBOX        = 'Inbox';
    case QUOTES       = 'Quote';
    case STAFFPM      = 'StaffPM';
    case TORRENTS     = 'Torrent';
    case COLLAGES     = 'Collage';
    case SUBSCRIPTIONS = 'Subscription';
    case GLOBALNOTICE = 'GlobalNotification';

    public function toString(): string {
        return $this->value;
    }
}
