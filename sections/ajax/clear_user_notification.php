<?php

$notifMan = new Gazelle\Manager\Notification($LoggedUser['ID']);
$user = new Gazelle\User($LoggedUser['ID']);

$Type = $_POST['type'];

switch($Type) {
    case 'Blog':
        $LoggedUser['LastReadBlog'] = $notifMan->clearBlog();
        break;
    case 'Collages':
        $notifMan->catchupAllCollages();
        break;
    case 'Global':
        $notifMan->clearGlobal();
        break;
    case 'Inbox':
        $user->markAllReadInbox();
        break;
    case 'News':
        $LoggedUser['LastReadNews'] = $notifMan->clearNews();
        break;
    case 'Quotes':
        $user->clearQuotes();
        break;
    case 'StaffPM':
        $user->markAllReadStaffPM();
        break;
    case 'Subscriptions':
        $notifMan->clearSubscriptions($UserID);
        break;
    case 'Torrents':
        $user->clearTorrentNotifications();
        break;
    default:
        if (strpos($Type, "oneread_") === 0) {
            $notifMan->clearOneRead($Type);
        }
        break;
}
