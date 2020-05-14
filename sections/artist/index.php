<?php
/**************************************************************************
Artists Switch Center

This page acts as a switch that includes the real artist pages (to keep
the root less cluttered).

enforce_login() is run here - the entire artist pages are off limits for
non members.
 ****************************************************************************/

// Width and height of similar artist map
define('WIDTH', 585);
define('HEIGHT', 400);

enforce_login();

if (!empty($_POST['action'])) {
    switch ($_POST['action']) {
        case 'edit':
            require(__DIR__ . '/takeedit.php');
            break;
        case 'download':
            require(__DIR__ . '/download.php');
            break;
        case 'rename':
            require(__DIR__ . '/rename.php');
            break;
        case 'add_similar':
            require(__DIR__ . '/add_similar.php');
            break;
        case 'add_alias':
            require(__DIR__ . '/add_alias.php');
            break;
        case 'change_artistid':
            require(__DIR__ . '/change_artistid.php');
            break;
        case 'takeeditrequest':
            include(__DIR__ . '/takeeditrequest.php');
            break;
        default:
            error(0);
    }
} elseif (!empty($_GET['action'])) {
    switch ($_GET['action']) {
        case 'autocomplete':
            require(__DIR__ . '/autocomplete.php');
            break;

        case 'edit':
            require(__DIR__ . '/edit.php');
            break;
        case 'delete':
            require(__DIR__ . '/delete.php');
            break;
        case 'revert':
            require(__DIR__ . '/takeedit.php');
            break;
        case 'history':
            require(__DIR__ . '/history.php');
            break;
        case 'vote_similar':
            require(__DIR__ . '/vote_similar.php');
            break;
        case 'delete_similar':
            require(__DIR__ . '/delete_similar.php');
            break;
        case 'similar':
            require(__DIR__ . '/similar.php');
            break;
        case 'similar_bg':
            require(__DIR__ . '/similar_bg.php');
            break;
        case 'notify':
            require(__DIR__ . '/notify.php');
            break;
        case 'notifyremove':
            require(__DIR__ . '/notifyremove.php');
            break;
        case 'delete_alias':
            require(__DIR__ . '/delete_alias.php');
            break;
        case 'change_artistid':
            require(__DIR__ . '/change_artistid.php');
            break;
        case 'editrequest':
            require(__DIR__ . '/editrequest.php');
            break;
        default:
            error(0);
            break;
    }
} else {
    if (!empty($_GET['id'])) {

        require(__DIR__ . '/artist.php');

    } elseif (!empty($_GET['artistname'])) {

        $NameSearch = str_replace('\\', '\\\\', trim($_GET['artistname']));
        $DB->query("
            SELECT ArtistID, Name
            FROM artists_alias
            WHERE Name LIKE '" . db_string($NameSearch) . "'");
        if (!$DB->has_results()) {
            if (isset($LoggedUser['SearchType']) && $LoggedUser['SearchType']) {
                header('Location: torrents.php?action=advanced&artistname=' . urlencode($_GET['artistname']));
            } else {
                header('Location: torrents.php?searchstr=' . urlencode($_GET['artistname']));
            }
            die();
        }
        list($FirstID, $Name) = $DB->next_record(MYSQLI_NUM, false);
        if ($DB->record_count() === 1 || !strcasecmp($Name, $NameSearch)) {
            header("Location: artist.php?id=$FirstID");
            die();
        }
        while (list($ID, $Name) = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($Name, $NameSearch)) {
                header("Location: artist.php?id=$ID");
                die();
            }
        }
        header("Location: artist.php?id=$FirstID");
        die();
    } else {
        header('Location: torrents.php');
    }
}
