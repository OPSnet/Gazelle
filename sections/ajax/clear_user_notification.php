<?php

$notification = new Gazelle\Manager\Notification($UserID);
$user = new Gazelle\User($UserID);

$Type = $_POST['type'];

switch($Type) {
    case Notification::BLOG:
        $LoggedUser['LastReadBlog'] = $notification->clearBlog();
        break;
    case Notification::COLLAGES:
        $user->clearCollages();
        break;
    case Notification::GLOBALNOTICE:
        $notification->clearGlobal();
        break;
    case Notification::INBOX:
        $user->markAllReadInbox();
        break;
    case Notification::NEWS:
        $LoggedUser['LastReadNews'] = $notification->clearNews();
        break;
    case Notification::QUOTES:
        $user->clearQuotes();
        break;
    case Notification::STAFFPM:
        $user->markAllReadStaffPM();
        break;
    case Notification::SUBSCRIPTIONS:
        $notification->clearSubscriptions($UserID);
        break;
    case Notification::TORRENTS:
        $user->clearTorrentNotifications();
        break;
    default:
        break;
}

if (strpos($Type, "oneread_") === 0) {
    $notification->clearOneRead($Type);
}
