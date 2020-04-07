<?php
define('ARTIST_COLLAGE', 'Artists');
enforce_login();

if (empty($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
    case 'new':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require(__DIR__ . '/new.php');
        break;
    case 'new_handle':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require(__DIR__ . '/new_handle.php');
        break;
    case 'add_torrent':
    case 'add_torrent_batch':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/add_torrent.php');
        break;
    case 'add_artist':
    case 'add_artist_batch':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/add_artist.php');
        break;
    case 'manage':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/manage.php');
        break;
    case 'manage_handle':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/manage_handle.php');
        break;
    case 'manage_artists':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/manage_artists.php');
        break;
    case 'manage_artists_handle':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require(__DIR__ . '/manage_artists_handle.php');
        break;
    case 'edit':
        if (!check_perms('site_edit_wiki')) {
            error(403);
        }
        require(__DIR__ . '/edit.php');
        break;
    case 'edit_handle':
        if (!check_perms('site_edit_wiki')) {
            error(403);
        }
        require(__DIR__ . '/edit_handle.php');
        break;
    case 'delete':
        authorize();
        require(__DIR__ . '/delete.php');
        break;
    case 'take_delete':
        require(__DIR__ . '/take_delete.php');
        break;
    case 'comments':
        require(__DIR__ . '/all_comments.php');
        break;
    case 'download':
        require(__DIR__ . '/download.php');
        break;
    case 'recover':
        require(__DIR__ . '/recover.php');
        break;
    case 'create_personal':
        if (!check_perms('site_collages_personal')) {
            error(403);
        } else {
            Collages::create_personal_collage();
        }
        break;

    default:
        require(__DIR__ . (empty($_GET['id']) ? '/browse.php' : '/collage.php'));
        break;
}
