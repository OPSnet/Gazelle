<?php

use Gazelle\Manager\Notification;

$UserID = $LoggedUser['ID'];

$notifMan = new Notification($UserID);
$user = new Gazelle\User($UserID);

$Type = $_POST['type'];

switch($Type) {
    case Notification::BLOG:
        $LoggedUser['LastReadBlog'] = $notifMan->clearBlog();
        break;
    case Notification::COLLAGES:
        $user->clearCollages();
        break;
    case Notification::GLOBALNOTICE:
        $notifMan->clearGlobal();
        break;
    case Notification::INBOX:
        $user->markAllReadInbox();
        break;
    case Notification::NEWS:
        $LoggedUser['LastReadNews'] = $notifMan->clearNews();
        break;
    case Notification::QUOTES:
        $user->clearQuotes();
        break;
    case Notification::STAFFPM:
        $user->markAllReadStaffPM();
        break;
    case Notification::SUBSCRIPTIONS:
        $notifMan->clearSubscriptions($UserID);
        break;
    case Notification::TORRENTS:
        $user->clearTorrentNotifications();
        break;
    default:
        break;
}

if (strpos($Type, "oneread_") === 0) {
    $notifMan->clearOneRead($Type);
}
