<?php

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'editlog':
            require_once('edit_log.php');
            break;
        case 'deletelog':
            require_once('delete_log.php');
            break;
        case 'take_editlog':
            require_once('take_edit_log.php');
            break;
        case 'rescore_log':
            require_once('rescore_log.php');
            break;
        case 'viewlog':
            require_once('log_ajax.php');
            break;
        case 'removelogs':
            require_once('remove_logs.php');
            break;
        case 'edit':
            require_once('edit.php');
            break;
        case 'editgroup':
            require_once('editgroup.php');
            break;
        case 'editgroupid':
            require_once('editgroupid.php');
            break;
        case 'changecategory':
            require_once('takechangecategory.php');
            break;
        case 'grouplog':
            require_once('grouplog.php');
            break;
        case 'takeedit':
            require_once('takeedit.php');
            break;
        case 'newgroup':
            require_once('takenewgroup.php');
            break;
        case 'peerlist':
            require_once('peerlist.php');
            break;
        case 'snatchlist':
            require_once('snatchlist.php');
            break;
        case 'download':
            require_once('download.php');
            break;
        case 'downloadlist':
            require_once('downloadlist.php');
            break;
        case 'redownload':
            require_once('redownload.php');
            break;
        case 'revert':
        case 'takegroupedit':
            require_once('takegroupedit.php');
            break;
        case 'nonwikiedit':
            require_once('nonwikiedit.php');
            break;
        case 'rename':
            require_once('rename.php');
            break;
        case 'merge':
            require_once('merge.php');
            break;
        case 'add_alias':
            require_once('add_alias.php');
            break;
        case 'delete_alias':
            require_once('delete_alias.php');
            break;
        case 'history':
            require_once('history.php');
            break;
        case 'delete':
            require_once('delete.php');
            break;
        case 'takedelete':
            require_once('takedelete.php');
            break;
        case 'masspm':
            require_once('masspm.php');
            break;
        case 'reseed':
            require_once('reseed.php');
            break;
        case 'takemasspm':
            require_once('takemasspm.php');
            break;
        case 'vote_tag':
            require_once('vote_tag.php');
            break;
        case 'add_tag':
            require_once('add_tag.php');
            break;
        case 'delete_tag':
            require_once('delete_tag.php');
            break;
        case 'notify':
            require_once('notify.php');
            break;
        case 'manage_artists':
            require_once('manage_artists.php');
            break;
        case 'editrequest':
            require_once('editrequest.php');
            break;
        case 'takeeditrequest':
            require_once('takeeditrequest.php');
            break;
        case 'notify_catchup':
        case 'notify_catchup_filter':
        case 'notify_clear':
        case 'notify_clear_filter':
        case 'notify_clear_item':
        case 'notify_clear_items':
            require_once('notify_actions.php');
            break;
        case 'collector':
            // NB: called from better.php
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
        case 'autocomplete_tags':
            require_once('autocomplete_tags.php');
            break;
        default:

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
    if (!empty($_GET['id'])) {
        require_once('details.php');
    } elseif (isset($_GET['torrentid'])) {
        $torrentId = (int)$_GET['torrentid'];
        $GroupID = $DB->scalar("
            SELECT GroupID FROM torrents WHERE ID = ?
            UNION
            SELECT GroupID FROM deleted_torrents WHERE ID = ?
            ", $torrentId, $torrentId
        );
        if ($GroupID) {
            header("Location: torrents.php?id=$GroupID&torrentid={$torrentId}#torrent{$torrentId}");
        } else {
            header("Location: log.php?search=Torrent+$torrentId");
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
