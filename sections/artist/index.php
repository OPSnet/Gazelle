<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!empty($_POST['action'])) {
    match ($_POST['action']) {
        'add_similar'     => require_once('add_similar.php'),
        'add_alias'       => require_once('add_alias.php'),
        'change_artistid' => require_once('change_artistid.php'),
        'download'        => require_once('download.php'),
        'rename'          => require_once('rename.php'),
        'edit'            => require_once('edit_handle.php'),
        'takeeditrequest' => require_once('edit_request_handle.php'),
        default           => error(0),
    };
} elseif (!empty($_GET['action'])) {
    match ($_GET['action']) {
        'autocomplete'    => require_once('autocomplete.php'),
        'change_artistid' => require_once('change_artistid.php'),
        'delete'          => require_once('delete.php'),
        'delete_alias'    => require_once('delete_alias.php'),
        'delete_similar'  => require_once('delete_similar.php'),
        'edit'            => require_once('edit.php'),
        'editrequest'     => require_once('edit_request.php'),
        'history'         => require_once('history.php'),
        'notify'          => require_once('notify.php'),
        'notifyremove'    => require_once('notify_remove.php'),
        'revert'          => require_once('edit_handle.php'),
        'vote_similar'    => require_once('vote_similar.php'),
        default           => error(0),
    };
} else {
    if (!empty($_GET['id'])) {
        require_once('artist.php');
    } elseif (empty($_GET['artistname'])) {
        header('Location: torrents.php');
    } else {
        $db = Gazelle\DB::DB();
        $NameSearch = str_replace('\\', '\\\\', trim($_GET['artistname']));
        $db->prepared_query("
            SELECT ArtistID, Name
            FROM artists_alias
            WHERE Name = ?
            ", $NameSearch
        );
        [$FirstID, $Name] = $db->next_record(MYSQLI_NUM, false);
        if (is_null($FirstID)) {
            if ($Viewer->permitted('site_advanced_search') && $Viewer->option('SearchType')) {
                header('Location: torrents.php?action=advanced&artistname=' . urlencode($_GET['artistname']));
            } else {
                header('Location: torrents.php?searchstr=' . urlencode($_GET['artistname']));
            }
            exit;
        }
        if ($db->record_count() === 1 || !strcasecmp($Name, $NameSearch)) {
            header("Location: artist.php?id=$FirstID");
            exit;
        }
        while ([$ID, $Name] = $db->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($Name, $NameSearch)) {
                header("Location: artist.php?id=$ID");
                exit;
            }
        }
        header("Location: artist.php?id=$FirstID");
    }
}
