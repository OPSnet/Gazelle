<?php

switch ($_REQUEST['action'] ?? '') {
    case 'add_torrent':
    case 'add_torrent_batch':
        require('add_torrent.php');
        break;
    case 'add_artist':
    case 'add_artist_batch':
        require('add_artist.php');
        break;
    case 'ajax_add':
        require('ajax_add.php');
        break;
    case 'autocomplete':
        require('autocomplete.php');
        break;
    case 'comments':
        require('all_comments.php');
        break;
    case 'delete':
        require('delete.php');
        break;
    case 'take_delete':
        require('take_delete.php');
        break;
    case 'download':
        require('download.php');
        break;
    case 'edit':
        if (!$Viewer->permitted('site_edit_wiki')) {
            error(403);
        }
        require('edit.php');
        break;
    case 'edit_handle':
        if (!$Viewer->permitted('site_edit_wiki')) {
            error(403);
        }
        require('edit_handle.php');
        break;
    case 'manage':
        if (!$Viewer->permitted('site_collages_manage')) {
            error(403);
        }
        require('manage.php');
        break;
    case 'manage_handle':
        if (!$Viewer->permitted('site_collages_manage')) {
            error(403);
        }
        require('manage_handle.php');
        break;
    case 'manage_artists':
        if (!$Viewer->permitted('site_collages_manage')) {
            error(403);
        }
        require('manage_artists.php');
        break;
    case 'manage_artists_handle':
        if (!$Viewer->permitted('site_collages_manage')) {
            error(403);
        }
        require('manage_artists_handle.php');
        break;
    case 'new':
        require('new.php');
        break;
    case 'new_handle':
        require('new_handle.php');
        break;
    case 'recover':
        require('recover.php');
        break;
    default:
        require(empty($_GET['id']) ? 'browse.php' : 'collage.php');
        break;
}
