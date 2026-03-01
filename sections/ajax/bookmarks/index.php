<?php

declare(strict_types=1);

namespace Gazelle;

switch ($_REQUEST['type'] ?? null) {
    case 'artists':
        require_once __DIR__ . '/artists.php';
        break;
    case 'collages':
        // FIXME: not implemented
        json_die('not implemented');
        //$_GET['bookmarks'] = 1;
        //require_once __DIR__ . '/browse.php';
        //break;
    case 'requests':
        $_GET['type'] = 'bookmarks';
        require_once __DIR__ . '/../requests.php';
        break;
    default:
        require_once __DIR__ . '/torrents.php';
        break;
}
