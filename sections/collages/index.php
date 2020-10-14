<?php

enforce_login();

switch ($_REQUEST['action'] ?? '') {
    case 'new':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require('new.php');
        break;
    case 'new_handle':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require('new_handle.php');
        break;
    case 'add_torrent':
    case 'add_torrent_batch':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('add_torrent.php');
        break;
    case 'add_artist':
    case 'add_artist_batch':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('add_artist.php');
        break;
    case 'manage':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('manage.php');
        break;
    case 'manage_handle':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('manage_handle.php');
        break;
    case 'manage_artists':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('manage_artists.php');
        break;
    case 'manage_artists_handle':
        if (!check_perms('site_collages_manage')) {
            error(403);
        }
        require('manage_artists_handle.php');
        break;
    case 'edit':
        if (!check_perms('site_edit_wiki')) {
            error(403);
        }
        require('edit.php');
        break;
    case 'edit_handle':
        if (!check_perms('site_edit_wiki')) {
            error(403);
        }
        require('edit_handle.php');
        break;
    case 'delete':
        require('delete.php');
        break;
    case 'take_delete':
        require('take_delete.php');
        break;
    case 'comments':
        require('all_comments.php');
        break;
    case 'download':
        require('download.php');
        break;
    case 'recover':
        require('recover.php');
        break;
    case 'create_personal':
        if (!check_perms('site_collages_personal')) {
            error(403);
        } else {
            Collages::create_personal_collage();
        }
        break;
    default:
        require(empty($_GET['id']) ? 'browse.php' : 'collage.php');
        break;
}
