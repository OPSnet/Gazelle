<?php

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'edit':
            require_once('edit.php');
            break;
        case 'takeedit':
            require_once('edit_handle.php');
            break;
        case 'changecategory':
            require_once('edit_category_handle.php');
            break;
        case 'editgroup':
            require_once('edit_group.php');
            break;
        case 'revert':
        case 'takegroupedit':
            require_once('edit_group_handle.php');
            break;
        case 'editgroupid':
            require_once('new_groupid.php');
            break;
        case 'newgroup':
            require_once('new_group_handle.php');
            break;
        case 'editrequest':
            require_once('edit_request.php');
            break;
        case 'takeeditrequest':
            require_once('edit_request_handle.php');
            break;
        case 'editlog':
            require_once('edit_log.php');
            break;
        case 'take_editlog':
            require_once('edit_log_handle.php');
            break;
        case 'rescore_log':
            require_once('rescore_log.php');
            break;
        case 'viewlog':
            require_once('log_ajax.php');
            break;
        case 'deletelog': // legacy name
        case 'removelog':
            require_once('remove_log.php');
            break;
        case 'removelogs':
            require_once('remove_logs.php');
            break;
        case 'grouplog':
            require_once('grouplog.php');
            break;
        case 'history':
            require_once('history.php');
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
        case 'delete':
            require_once('delete.php');
            break;
        case 'takedelete':
            require_once('delete_handle.php');
            break;
        case 'masspm':
            require_once('masspm.php');
            break;
        case 'takemasspm':
            require_once('masspm_handle.php');
            break;
        case 'reseed':
            require_once('reseed.php');
            break;
        case 'vote_tag':
            require_once('vote_tag.php');
            break;
        case 'manage_artists':
            require_once('manage_artists.php');
            break;
        case 'notify':
            require_once('notify.php');
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
            require_once('regen.php');
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
            } elseif (isset($_GET['torrentid'])) {
                $torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
                if ($torrent) {
                    header('Location: ' . $torrent->location());
                } else {
                    header("Location: log.php?search=Torrent+" . $_GET['torrentid']);
                }
            } else {
                require_once('browse.php');
            }
            break;
    }
} else {
    $manager = new \Gazelle\Manager\TGroup;
    if (!empty($_GET['id'])) {
        require_once('details.php');
    } elseif (isset($_GET['torrentid'])) {
        $torrentId = (int)$_GET['torrentid'];
        $tgroup = $manager->findByTorrentId($torrentId);
        if ($tgroup) {
            header("Location: torrents.php?id={$tgroup->id()}&torrentid={$torrentId}#torrent{$torrentId}");
        } else {
            header("Location: log.php?search=Torrent+$torrentId");
        }
    } elseif (!empty($_GET['type'])) {
        require_once('user.php');
    } elseif (!empty($_GET['groupname'])) {
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            SELECT ID FROM torrents_group WHERE Name = ? LIMIT 2
            ", trim($_GET['groupname'])
        );
        $list = $db->collect(0);
        if (count($list) == 1) {
            header("Location: torrents.php?id=$list[0]");
        } else {
            header("Location: torrents.php?action=advanced&groupname=" . trim($_GET['groupname']));
        }
    } else {
        require_once('browse.php');
    }
}
