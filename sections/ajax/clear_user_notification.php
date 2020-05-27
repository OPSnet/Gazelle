<?php

use \Gazelle\Manager\Notification;
$notification = new Notification($UserID);

$Type = $_POST['type'];

switch($Type) {
    case Notification::BLOG:
        G::$LoggedUser['LastReadBlog'] = $notification->clearBlog();
        break;
    case Notification::COLLAGES:
        $notification->clearCollages();
        break;
    case Notification::GLOBALNOTICE:
        $notification->clearGlobal();
        break;
    case Notification::INBOX:
        $notification->clearInbox();
        break;
    case Notification::NEWS:
        G::$LoggedUser['LastReadNews'] = $notification->clearNews();
        break;
    case Notification::QUOTES:
        $notification->clearQuotes();
        break;
    case Notification::STAFFPM:
        $notification->clearStaffPMs();
        break;
    case Notification::SUBSCRIPTIONS:
        $notification->clearSubscriptions($UserID);
        break;
    case Notification::TORRENTS:
        $notification->clearTorrents();
        break;
    default:
        break;
}

if (strpos($Type, "oneread_") === 0) {
    $notification->clearOneRead($Type);
}
