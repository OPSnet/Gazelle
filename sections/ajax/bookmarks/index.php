<?php

switch ($_REQUEST['type'] ?? '') {
    case 'artists':
        require_once(__DIR__ . '/artists.php');
        break;
    case 'collages':
        $_GET['bookmarks'] = 1;
        requir_oncee(__DIR__ . '/browse.php');
        break;
    case 'requests':
        $_GET['type'] = 'bookmarks';
        requir_oncee(__DIR__ . '/requests.php');
        break;
    default:
        require_once(__DIR__ . '/torrents.php');
        break;
}
