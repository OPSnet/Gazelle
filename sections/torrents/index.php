<?php

//Function used for pagination of peer/snatch/download lists on details.php
function js_pages($Action, $TorrentID, $NumResults, $CurrentPage) {
    $NumPages = ceil($NumResults / 100);
    $PageLinks = [];
    for ($i = 1; $i <= $NumPages; $i++) {
        if ($i == $CurrentPage) {
            $PageLinks[] = $i;
        } else {
            $PageLinks[] = "<a href=\"#\" onclick=\"$Action($TorrentID, $i)\">$i</a>";
        }
    }
    return implode(' | ', $PageLinks);
}

// This gets used in a few places
$ArtistTypes = [
    1 => 'Main',
    2 => 'Guest',
    3 => 'Remixer',
    4 => 'Composer',
    5 => 'Conductor',
    6 => 'DJ/Compiler',
    7 => 'Producer',
    8 => 'Arranger',
];

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'editlog':
            enforce_login();
            require_once('edit_log.php');
            break;

        case 'deletelog':
            enforce_login();
            require_once('delete_log.php');
            break;

        case 'take_editlog':
            enforce_login();
            require_once('take_edit_log.php');
            break;

        case 'rescore_log':
            enforce_login();
            require_once('rescore_log.php');
            break;

        case 'viewlog':
            enforce_login();
            require_once('log_ajax.php');
            break;

        case 'removelogs':
            enforce_login();
            require_once('remove_logs.php');
            break;

        case 'edit':
            enforce_login();
            require_once('edit.php');
            break;

        case 'editgroup':
            enforce_login();
            require_once('editgroup.php');
            break;

        case 'editgroupid':
            enforce_login();
            require_once('editgroupid.php');
            break;

        case 'changecategory':
            enforce_login();
            require_once('takechangecategory.php');
            break;
        case 'grouplog':
            enforce_login();
            require_once('grouplog.php');
            break;
        case 'takeedit':
            enforce_login();
            require_once('takeedit.php');
            break;

        case 'newgroup':
            enforce_login();
            require_once('takenewgroup.php');
            break;

        case 'peerlist':
            enforce_login();
            require_once('peerlist.php');
            break;

        case 'snatchlist':
            enforce_login();
            require_once('snatchlist.php');
            break;

        case 'download':
            require_once('download.php');
            break;

        case 'downloadlist':
            enforce_login();
            require_once('downloadlist.php');
            break;

        case 'redownload':
            enforce_login();
            require_once('redownload.php');
            break;

        case 'revert':
        case 'takegroupedit':
            enforce_login();
            require_once('takegroupedit.php');
            break;

        case 'nonwikiedit':
            enforce_login();
            require_once('nonwikiedit.php');
            break;

        case 'rename':
            enforce_login();
            require_once('rename.php');
            break;

        case 'merge':
            enforce_login();
            require_once('merge.php');
            break;

        case 'add_alias':
            enforce_login();
            require_once('add_alias.php');
            break;

        case 'delete_alias':
            enforce_login();
            authorize();
            require_once('delete_alias.php');
            break;

        case 'history':
            enforce_login();
            require_once('history.php');
            break;

        case 'delete':
            enforce_login();
            require_once('delete.php');
            break;

        case 'takedelete':
            enforce_login();
            require_once('takedelete.php');
            break;

        case 'masspm':
            enforce_login();
            require_once('masspm.php');
            break;

        case 'reseed':
            enforce_login();
            require_once('reseed.php');
            break;

        case 'takemasspm':
            enforce_login();
            require_once('takemasspm.php');
            break;

        case 'vote_tag':
            enforce_login();
            authorize();
            require_once('vote_tag.php');
            break;

        case 'add_tag':
            enforce_login();
            require_once('add_tag.php');
            break;

        case 'delete_tag':
            enforce_login();
            authorize();
            require_once('delete_tag.php');
            break;

        case 'notify':
            enforce_login();
            require_once('notify.php');
            break;

        case 'manage_artists':
            enforce_login();
            require_once('manage_artists.php');
            break;

        case 'editrequest':
            enforce_login();
            require_once('editrequest.php');
            break;

        case 'takeeditrequest':
            enforce_login();
            require_once('takeeditrequest.php');
            break;

        case 'notify_clear':
        case 'notify_clear_item':
        case 'notify_clear_items':
        case 'notify_clearitem':
        case 'notify_clear_filter':
        case 'notify_cleargroup':
        case 'notify_catchup':
        case 'notify_catchup_filter':
            authorize();
            enforce_login();
            require_once('notify_actions.php');
            break;

        case 'collector':
            // NB: called from better.php
            enforce_login();
            require_once('collector.php');
            break;

        case 'regen_filelist':
            $torrentId = (int)($_REQUEST['torrentid'] ?? 0);
            if ($torrentId && check_perms('users_mod')) {
                (new Gazelle\Manager\Torrent)->regenerateFilelist($torrentId);
                header("Location: torrents.php?torrentid=$torrentId");
                exit;
            }
            error(403);
            break;
        case 'add_cover_art':
            require_once('add_cover_art.php');
            break;
        case 'remove_cover_art':
            require_once('remove_cover_art.php');
            break;
        case 'autocomplete_tags':
            require_once('autocomplete_tags.php');
            break;
        default:
            enforce_login();

            if (!empty($_GET['id'])) {
                require_once('details.php');
            } elseif (isset($_GET['torrentid']) && is_number($_GET['torrentid'])) {
                $GroupID = $DB->scalar("
                    SELECT GroupID FROM torrents WHERE ID = ?
                    ", $_GET['torrentid']
                );
                if ($GroupID) {
                    header("Location: torrents.php?id=$GroupID&torrentid=".$_GET['torrentid'].'#torrent'.$_GET['torrentid']);
                } else {
                    header("Location: log.php?search=Torrent+" . $_GET['torrentid']);
                }
            } else {
                require_once('browse.php');
            }
            break;
    }
} else {
    enforce_login();

    if (!empty($_GET['id'])) {
        require_once('details.php');
    } elseif (isset($_GET['torrentid']) && intval($_GET['torrentid'])) {
        $torrentId = (int)$_GET['torrentid'];
        $this->db->prepared("
            SELECT GroupID
            FROM torrents
            WHERE ID = ?
            UNION
            SELECT GroupID
            FROM deleted_torrents
            WHERE ID = ?
            ", $torrentId, $torrentId
        );
        if ($GroupID) {
            header("Location: torrents.php?id=$GroupID&torrentid=".$_GET['torrentid'].'#torrent'.$_GET['torrentid']);
        } else {
            header("Location: log.php?search=Torrent+" . $_GET['torrentid']);
        }
    } elseif (!empty($_GET['type'])) {
        require_once('user.php');
    } elseif (!empty($_GET['groupname'])) {
        $DB->prepared_query("
            SELECT ID FROM torrents_group WHERE Name = ? LIMIT 2
            ", trim($_GET['groupname'])
        );
        $list = $DB->collect(0);
        if (count($list) == 1) {
            header("Location: torrents.php?id=$list[0]");
        } else {
            header("Location: torrents.php?action=advanced&groupname=" . trim($_GET['groupname']));
        }
    } else {
        require_once('browse.php');
    }
}
