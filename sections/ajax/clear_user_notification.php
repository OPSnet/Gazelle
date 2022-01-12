<?php

$Type = $_POST['type'];

switch($Type) {
    case 'Blog':
        (new Gazelle\User\Notification\Blog($Viewer))->clear();
        break;
    case 'Collages':
        (new Gazelle\User\Notification\Collage($Viewer))->clear();
        break;
    case 'Global':
        (new Gazelle\User\Notification\GlobalNotification($Viewer))->clear();
        break;
    case 'Inbox':
        (new Gazelle\User\Notification\Inbox($Viewer))->clear();
        break;
    case 'News':
        (new Gazelle\User\Notification\News($Viewer))->clear();
        break;
    case 'Quotes':
        (new Gazelle\User\Notification\Quote($Viewer))->clear();
        break;
    case 'StaffPM':
        (new Gazelle\User\Notification\StaffPM($Viewer))->clear();
        break;
    case 'Subscriptions':
        (new Gazelle\User\Notification\Subscription($Viewer))->clear();
        break;
    case 'Torrents':
        (new Gazelle\User\Notification\Torrent($Viewer))->clear();
        break;
    default:
        error(0);
        break;
}
