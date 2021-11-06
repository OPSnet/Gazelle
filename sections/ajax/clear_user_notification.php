<?php

$notifMan = new Gazelle\Manager\Notification($Viewer->id());

$Type = $_POST['type'];

switch($Type) {
    case 'Blog':
        (new Gazelle\WitnessTable\UserReadBlog)->witness($Viewer->id());
        break;
    case 'Collages':
        $notifMan->catchupAllCollages();
        break;
    case 'Global':
        $notifMan->clearGlobal();
        break;
    case 'Inbox':
        $Viewer->markAllReadInbox();
        break;
    case 'News':
        $notifMan->clearNews();
        break;
    case 'Quotes':
        (new Gazelle\User\Quote($Viewer))->clear();
        break;
    case 'StaffPM':
        $Viewer->markAllReadStaffPM();
        break;
    case 'Subscriptions':
        $notifMan->clearSubscriptions($Viewer->id());
        break;
    case 'Torrents':
        $Viewer->clearTorrentNotifications();
        break;
    default:
        if (strpos($Type, "oneread_") === 0) {
            $notifMan->clearOneRead($Type);
        }
        break;
}
