<?php

switch ($_REQUEST['action'] ?? 'view') {
    case 'add':
        require_once('add.php');
        break;

    case 'remove':
        require_once('remove.php');
        break;

    case 'mass_edit':
        require_once('mass_edit.php');
        break;

    case 'remove_snatched':
        require_once('remove_snatched.php');
        break;

    case 'edit':
        match ($_REQUEST['type'] ?? '') {
            'torrents' => require_once('edit_torrents.php'),
            default    => error(404),
        };
        break;

    case 'view':
        switch ($_REQUEST['type'] ?? 'torrents') {
            case 'torrents':
                require_once('torrents.php');
                break;
            case 'artists':
                require_once('artists.php');
                break;
            case 'collages':
                $_GET['bookmarks'] = '1';
                require_once(__DIR__ . '/../collages/browse.php');
                break;
            case 'requests':
                $_GET['type'] = 'bookmarks';
                require_once(__DIR__ . '/../requests/requests.php');
                break;
            default:
                error(404);
        }
        break;

    default:
        error(404);
}
