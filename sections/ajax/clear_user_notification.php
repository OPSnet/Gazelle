<?php

use \Gazelle\Manager\Notification;

$Type = $_POST['type'];

switch($Type) {
    case Notification::INBOX:
        Notification::clear_inbox();
        break;
    case Notification::NEWS:
        Notification::clear_news();
        break;
    case Notification::BLOG:
        Notification::clear_blog();
        break;
    case Notification::STAFFPM:
        Notification::clear_staff_pms();
        break;
    case Notification::TORRENTS:
        Notification::clear_torrents();
        break;
    case Notification::QUOTES:
        Notification::clear_quotes();
        break;
    case Notification::SUBSCRIPTIONS:
        Notification::clear_subscriptions();
        break;
    case Notification::COLLAGES:
        Notification::clear_collages();
        break;
    case Notification::GLOBALNOTICE:
        Notification::clear_global_notification();
        break;
    default:
        break;
}

if (strpos($Type, "oneread_") === 0) {
    Notification::clear_one_read($Type);
}
