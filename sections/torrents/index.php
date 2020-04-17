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
$ArtistTypes = [1 => 'Main', 2 => 'Guest', 3 => 'Remixer', 4 => 'Composer', 5 => 'Conductor', 6 => 'DJ/Compiler', 7 => 'Producer'];

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'editlog':
            enforce_login();
            require(__DIR__ . '/edit_log.php');
            break;

        case 'deletelog':
            enforce_login();
            require(__DIR__ . '/delete_log.php');
            break;

        case 'take_editlog':
            enforce_login();
            require(__DIR__ . '/take_edit_log.php');
            break;

        case 'rescore_log':
            enforce_login();
            require(__DIR__ . '/rescore_log.php');
            break;

        case 'viewlog':
            enforce_login();
            require(__DIR__ . '/log_ajax.php');
            break;

        case 'removelogs':
            enforce_login();
            require(__DIR__ . '/remove_logs.php');
            break;

        case 'edit':
            enforce_login();
            require(__DIR__ . '/edit.php');
            break;

        case 'editgroup':
            enforce_login();
            require(__DIR__ . '/editgroup.php');
            break;

        case 'editgroupid':
            enforce_login();
            require(__DIR__ . '/editgroupid.php');
            break;

        case 'changecategory':
            enforce_login();
            require(__DIR__ . '/takechangecategory.php');
            break;
        case 'grouplog':
            enforce_login();
            require(__DIR__ . '/grouplog.php');
            break;
        case 'takeedit':
            enforce_login();
            require(__DIR__ . '/takeedit.php');
            break;

        case 'newgroup':
            enforce_login();
            require(__DIR__ . '/takenewgroup.php');
            break;

        case 'peerlist':
            enforce_login();
            require(__DIR__ . '/peerlist.php');
            break;

        case 'snatchlist':
            enforce_login();
            require(__DIR__ . '/snatchlist.php');
            break;

        case 'downloadlist':
            enforce_login();
            require(__DIR__ . '/downloadlist.php');
            break;

        case 'redownload':
            enforce_login();
            require(__DIR__ . '/redownload.php');
            break;

        case 'revert':
        case 'takegroupedit':
            enforce_login();
            require(__DIR__ . '/takegroupedit.php');
            break;

        case 'nonwikiedit':
            enforce_login();
            require(__DIR__ . '/nonwikiedit.php');
            break;

        case 'rename':
            enforce_login();
            require(__DIR__ . '/rename.php');
            break;

        case 'merge':
            enforce_login();
            require(__DIR__ . '/merge.php');
            break;

        case 'add_alias':
            enforce_login();
            require(__DIR__ . '/add_alias.php');
            break;

        case 'delete_alias':
            enforce_login();
            authorize();
            require(__DIR__ . '/delete_alias.php');
            break;

        case 'history':
            enforce_login();
            require(__DIR__ . '/history.php');
            break;

        case 'delete':
            enforce_login();
            require(__DIR__ . '/delete.php');
            break;

        case 'takedelete':
            enforce_login();
            require(__DIR__ . '/takedelete.php');
            break;

        case 'masspm':
            enforce_login();
            require(__DIR__ . '/masspm.php');
            break;

        case 'reseed':
            enforce_login();
            require(__DIR__ . '/reseed.php');
            break;

        case 'takemasspm':
            enforce_login();
            require(__DIR__ . '/takemasspm.php');
            break;

        case 'vote_tag':
            enforce_login();
            authorize();
            require(__DIR__ . '/vote_tag.php');
            break;

        case 'add_tag':
            enforce_login();
            require(__DIR__ . '/add_tag.php');
            break;

        case 'delete_tag':
            enforce_login();
            authorize();
            require(__DIR__ . '/delete_tag.php');
            break;

        case 'notify':
            enforce_login();
            require(__DIR__ . '/notify.php');
            break;

        case 'manage_artists':
            enforce_login();
            require(__DIR__ . '/manage_artists.php');
            break;

        case 'editrequest':
            enforce_login();
            require(__DIR__ . '/editrequest.php');
            break;

        case 'takeeditrequest':
            enforce_login();
            require(__DIR__ . '/takeeditrequest.php');
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
            require(__DIR__ . '/notify_actions.php');
            break;

        case 'download':
            require(__DIR__ . '/download.php');
            break;

        case 'collector':
            enforce_login();
            require(__DIR__ . '/collector.php');
            break;

        case 'regen_filelist':
            if (check_perms('users_mod') && !empty($_GET['torrentid']) && is_number($_GET['torrentid'])) {
                Torrents::regenerate_filelist($_GET['torrentid']);
                header('Location: torrents.php?torrentid='.$_GET['torrentid']);
                die();
            } else {
                error(403);
            }
            break;
        case 'fix_group':
            if ((check_perms('users_mod') || check_perms('torrents_fix_ghosts')) && authorize() && !empty($_GET['groupid']) && is_number($_GET['groupid'])) {
                $DB->query('
                    SELECT COUNT(ID)
                    FROM torrents
                    WHERE GroupID = '.$_GET['groupid']);
                list($Count) = $DB->next_record();
                if ($Count == 0) {
                    Torrents::delete_group($_GET['groupid']);
                } else {
                }
                if (!empty($_GET['artistid']) && is_number($_GET['artistid'])) {
                    header('Location: artist.php?id='.$_GET['artistid']);
                } else {
                    header('Location: torrents.php?id='.$_GET['groupid']);
                }
            } else {
                error(403);
            }
            break;
        case 'add_cover_art':
            require(__DIR__ . '/add_cover_art.php');
            break;
        case 'remove_cover_art':
            require(__DIR__ . '/remove_cover_art.php');
            break;
        case 'autocomplete_tags':
            require(__DIR__ . '/autocomplete_tags.php');
            break;
        default:
            enforce_login();

            if (!empty($_GET['id'])) {
                require(__DIR__ . '/details.php');
            } elseif (isset($_GET['torrentid']) && is_number($_GET['torrentid'])) {
                $DB->query('
                    SELECT GroupID
                    FROM torrents
                    WHERE ID = '.$_GET['torrentid']);
                list($GroupID) = $DB->next_record();
                if ($GroupID) {
                    header("Location: torrents.php?id=$GroupID&torrentid=".$_GET['torrentid']);
                }
            } else {
                require(__DIR__ . '/browse.php');
            }
            break;
    }
} else {
    enforce_login();

    if (!empty($_GET['id'])) {
        require(__DIR__ . '/details.php');
    } elseif (isset($_GET['torrentid']) && intval($_GET['torrentid'])) {
        $torrent_id = (int)$_GET['torrentid'];
        $DB->prepared_query('
            SELECT GroupID
            FROM torrents
            WHERE ID = ?
            UNION
            SELECT GroupID
            FROM deleted_torrents
            WHERE ID = ?
            ', $torrent_id, $torrent_id);
        list($GroupID) = $DB->next_record();
        if ($GroupID) {
            header("Location: torrents.php?id=$GroupID&torrentid=".$_GET['torrentid'].'#torrent'.$_GET['torrentid']);
        } else {
            header("Location: log.php?search=Torrent+$_GET[torrentid]");
        }
    } elseif (!empty($_GET['type'])) {
        require(__DIR__ . '/user.php');
    } elseif (!empty($_GET['groupname']) && !empty($_GET['forward'])) {
        $DB->prepared_query('
            SELECT ID
            FROM torrents_group
            WHERE Name LIKE ?', trim($_GET['groupname']));
        list($GroupID) = $DB->next_record();
        if ($GroupID) {
            header("Location: torrents.php?id=$GroupID");
        } else {
            require(__DIR__ . '/browse.php');
        }
    } else {
        require(__DIR__ . '/browse.php');
    }
}
