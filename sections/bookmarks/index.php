<?php
enforce_login();

define('BOOKMARKS_PER_PAGE', '20');

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
        authorize();
        $DB->query("
            CREATE TEMPORARY TABLE snatched_groups_temp
                (GroupID int PRIMARY KEY)");
        $DB->query("
            INSERT INTO snatched_groups_temp
            SELECT DISTINCT GroupID
            FROM torrents AS t
                JOIN xbt_snatched AS s ON s.fid = t.ID
            WHERE s.uid = '$LoggedUser[ID]'");
        $DB->query("
            DELETE b
            FROM bookmarks_torrents AS b
                JOIN snatched_groups_temp AS s
            USING(GroupID)
            WHERE b.UserID = '$LoggedUser[ID]'");
        $Cache->delete_value("bookmarks_group_ids_" . $LoggedUser['ID']);
        header('Location: bookmarks.php');
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
