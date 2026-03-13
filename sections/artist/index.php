<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (isset($_POST['action'])) {
    require __DIR__ . match ($_POST['action']) {
        'add_similar'     => '/add_similar.php',
        'add_alias'       => '/add_alias.php',
        'change_artistid' => '/change_artistid.php',
        'download'        => '/download.php',
        'rename'          => '/rename.php',
        'edit'            => '/edit_handle.php',
        'takeeditrequest' => '/edit_request_handle.php',
        default           => Error400::error('Missing artist POST action'),
    };
} elseif (isset($_GET['action'])) {
    require __DIR__ . match ($_GET['action']) {
        'autocomplete'    => '/autocomplete.php',
        'change_artistid' => '/change_artistid.php',
        'delete'          => '/delete.php',
        'delete_alias'    => '/delete_alias.php',
        'delete_similar'  => '/delete_similar.php',
        'edit'            => '/edit.php',
        'editrequest'     => '/edit_request.php',
        'history'         => '/history.php',
        'notify'          => '/notify.php',
        'notifyremove'    => '/notify_remove.php',
        'revert'          => '/edit_handle.php',
        'vote_similar'    => '/vote_similar.php',
        default           => Error400::error('Missing artist action'),
    };
} else {
    if (isset($_GET['id'])) {
        require __DIR__ . '/artist.php';
    } elseif (!isset($_GET['artistname'])) {
        header('Location: torrents.php');
    } else {
        $db = DB::DB();
        $NameSearch = str_replace('\\', '\\\\', trim($_GET['artistname']));
        $db->prepared_query("
            SELECT ArtistID, Name
            FROM artists_alias
            WHERE Name = ?
            ", $NameSearch
        );
        [$FirstID, $Name] = $db->next_record(MYSQLI_NUM);
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
        while ([$ID, $Name] = $db->next_record(MYSQLI_NUM)) {
            if (!strcasecmp($Name, $NameSearch)) {
                header("Location: artist.php?id=$ID");
                exit;
            }
        }
        header("Location: artist.php?id=$FirstID");
    }
}
