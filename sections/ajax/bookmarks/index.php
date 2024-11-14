<?php

switch ($_REQUEST['type'] ?? '') {
    case 'artists':
        include_once __DIR__ . '/artists.php';
        break;
    case 'collages':
        $_GET['bookmarks'] = 1;
        include_once __DIR__ . '/browse.php';
        break;
    case 'requests':
        $_GET['type'] = 'bookmarks';
        include_once __DIR__ . '/requests.php';
        break;
    default:
        include_once __DIR__ . '/torrents.php';
        break;
}
