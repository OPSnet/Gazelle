<?php

switch ($_REQUEST['action'] ?? 'view') {
    case 'add':
        require('add.php');
        break;

    case 'remove':
        require('remove.php');
        break;

    case 'mass_edit':
        require('mass_edit.php');
        break;

    case 'remove_snatched':
        require('remove_snatched.php');
        break;

    case 'edit':
        if (empty($_REQUEST['type'])) {
            $_REQUEST['type'] = false;
        }
        switch ($_REQUEST['type']) {
            case 'torrents':
                require('edit_torrents.php');
                break;
            default:
                error(404);
        }
        break;

    case 'view':
        switch ($_REQUEST['type'] ?? 'torrents') {
            case 'torrents':
                require('torrents.php');
                break;
            case 'artists':
                require('artists.php');
                break;
            case 'collages':
                $_GET['bookmarks'] = '1';
                require(__DIR__ . '/../collages/browse.php');
                break;
            case 'requests':
                $_GET['type'] = 'bookmarks';
                require(__DIR__ . '/../requests/requests.php');
                break;
            default:
                error(404);
        }
        break;

    default:
        error(404);
}
