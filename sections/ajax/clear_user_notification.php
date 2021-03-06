<?php

$notifMan = new Gazelle\Manager\Notification($LoggedUser['ID']);
$user = new Gazelle\User($LoggedUser['ID']);

$Type = $_POST['type'];

switch($Type) {
    case 'Blog':
        if ((new \Gazelle\WitnessTable\UserReadBlog)->witness($LoggedUser['ID'])) {
            $Cache->delete_value('user_info_heavy_' . $LoggedUser['ID']);
        }
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
        $notifMan->clearNews();
        break;
    case 'Quotes':
        (new Gazelle\User\Quote($user))->clear();
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
