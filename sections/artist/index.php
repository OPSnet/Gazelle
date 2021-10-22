<?php

if (!empty($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_similar':
            require_once('add_similar.php');
            break;
        case 'add_alias':
            require_once('add_alias.php');
            break;
        case 'change_artistid':
            require_once('change_artistid.php');
            break;
        case 'download':
            require_once('download.php');
            break;
        case 'rename':
            require_once('rename.php');
            break;
        case 'edit':
            require_once('takeedit.php');
            break;
        case 'takeeditrequest':
            require_once('takeeditrequest.php');
            break;
        default:
            error(0);
    }
} elseif (!empty($_GET['action'])) {
    switch ($_GET['action']) {
        case 'autocomplete':
            require_once('autocomplete.php');
            break;
        case 'change_artistid':
            require_once('change_artistid.php');
            break;
        case 'delete':
            require_once('delete.php');
            break;
        case 'delete_alias':
            require_once('delete_alias.php');
            break;
        case 'delete_similar':
            require_once('delete_similar.php');
            break;
        case 'edit':
            require_once('edit.php');
            break;
        case 'editrequest':
            require_once('editrequest.php');
            break;
        case 'history':
            require_once('history.php');
            break;
        case 'notify':
            require_once('notify.php');
            break;
        case 'notifyremove':
            require_once('notifyremove.php');
            break;
        case 'revert':
            require_once('takeedit.php');
            break;
        case 'vote_similar':
            require_once('vote_similar.php');
            break;
        default:
            error(0);
            break;
    }
} else {
    if (!empty($_GET['id'])) {
        require_once('artist.php');
    } elseif (empty($_GET['artistname'])) {
        header('Location: torrents.php');
    } else {
        $NameSearch = str_replace('\\', '\\\\', trim($_GET['artistname']));
        $DB->prepared_query("
            SELECT ArtistID, Name
            FROM artists_alias
            WHERE Name = ?
            ", $NameSearch
        );
        [$FirstID, $Name] = $DB->next_record(MYSQLI_NUM, false);
        if (is_null($FirstID)) {
            if ($Viewer->option('SearchType')) {
                header('Location: torrents.php?action=advanced&artistname=' . urlencode($_GET['artistname']));
            } else {
                header('Location: torrents.php?searchstr=' . urlencode($_GET['artistname']));
            }
            die();
        }
        if ($DB->record_count() === 1 || !strcasecmp($Name, $NameSearch)) {
            header("Location: artist.php?id=$FirstID");
            die();
        }
        while ([$ID, $Name] = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($Name, $NameSearch)) {
                header("Location: artist.php?id=$ID");
                die();
            }
        }
        header("Location: artist.php?id=$FirstID");
    }
}
