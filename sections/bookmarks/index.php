<?php

declare(strict_types=1);

namespace Gazelle;

switch ($_REQUEST['action'] ?? 'view') {
    case 'add':
        require __DIR__ . '/add.php';
        break;

    case 'remove':
        require __DIR__ . '/remove.php';
        break;

    case 'mass_edit':
        require __DIR__ . '/mass_edit.php';
        break;

    case 'remove_snatched':
        require __DIR__ . '/remove_snatched.php';
        break;

    case 'edit':
        match ($_REQUEST['type'] ?? '') {
            'torrents' => require __DIR__ . '/edit_torrents.php',
            default    => Error404::error(),
        };
        break;

    case 'view':
        switch ($_REQUEST['type'] ?? 'torrents') {
            case 'torrents':
                require __DIR__ . '/torrents.php';
                break;
            case 'artists':
                require __DIR__ . '/artists.php';
                break;
            case 'collages':
                $_GET['bookmarks'] = '1';
                require __DIR__ . '/../collages/browse.php';
                break;
            case 'requests':
                $_GET['type'] = 'bookmarks';
                require __DIR__ . '/../requests/requests.php';
                break;
            default:
                Error404::error();
        }
        break;

    default:
        Error404::error();
}
