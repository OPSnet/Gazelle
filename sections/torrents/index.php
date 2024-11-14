<?php

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'edit':
            include_once 'edit.php';
            break;
        case 'takeedit':
            include_once 'edit_handle.php';
            break;
        case 'changecategory':
            include_once 'edit_category_handle.php';
            break;
        case 'editgroup':
            include_once 'edit_group.php';
            break;
        case 'revert':
        case 'takegroupedit':
            include_once 'edit_group_handle.php';
            break;
        case 'editgroupid':
            include_once 'new_groupid.php';
            break;
        case 'newgroup':
            include_once 'new_group_handle.php';
            break;
        case 'editrequest':
            include_once 'edit_request.php';
            break;
        case 'takeeditrequest':
            include_once 'edit_request_handle.php';
            break;
        case 'editlog':
            include_once 'edit_log.php';
            break;
        case 'take_editlog':
            include_once 'edit_log_handle.php';
            break;
        case 'filelist':
            include_once 'filelist.php';
            break;
        case 'rescore_log':
            include_once 'rescore_log.php';
            break;
        case 'viewlog':
            include_once 'log_ajax.php';
            break;
        case 'deletelog': // legacy name
        case 'removelog':
            include_once 'remove_log.php';
            break;
        case 'removelogs':
            include_once 'remove_logs.php';
            break;
        case 'grouplog':
            include_once 'grouplog.php';
            break;
        case 'history':
            include_once 'history.php';
            break;
        case 'peerlist':
            include_once 'peerlist.php';
            break;
        case 'snatchlist':
            include_once 'snatchlist.php';
            break;
        case 'download':
            include_once 'download.php';
            break;
        case 'downloadlist':
            include_once 'downloadlist.php';
            break;
        case 'redownload':
            include_once 'redownload.php';
            break;
        case 'nonwikiedit':
            include_once 'nonwikiedit.php';
            break;
        case 'rename':
            include_once 'rename.php';
            break;
        case 'merge':
            include_once 'merge.php';
            break;
        case 'add_alias':
            include_once 'add_alias.php';
            break;
        case 'delete_alias':
            include_once 'delete_alias.php';
            break;
        case 'delete':
            include_once 'delete.php';
            break;
        case 'takedelete':
            include_once 'delete_handle.php';
            break;
        case 'masspm':
            include_once 'masspm.php';
            break;
        case 'takemasspm':
            include_once 'masspm_handle.php';
            break;
        case 'reseed':
            include_once 'reseed.php';
            break;
        case 'vote_tag':
            include_once 'vote_tag.php';
            break;
        case 'manage_artists':
            include_once 'manage_artists.php';
            break;
        case 'notify':
            include_once 'notify.php';
            break;
        case 'notify_catchup':
        case 'notify_catchup_filter':
        case 'notify_clear':
        case 'notify_clear_filter':
        case 'notify_clear_item':
        case 'notify_clear_items':
            include_once 'notify_actions.php';
            break;
        case 'collector':
            // NB: called from better.php
            include_once 'collector.php';
            break;
        case 'regen_filelist':
            include_once 'regen.php';
            break;
        case 'add_cover_art':
            include_once 'add_cover_art.php';
            break;
        case 'autocomplete_tags':
            include_once 'autocomplete_tags.php';
            break;

        default:
            if (!empty($_GET['id'])) {
                include_once 'details.php';
            } elseif (isset($_GET['torrentid'])) {
                $torrent = (new Gazelle\Manager\Torrent())->findById((int)$_GET['torrentid']);
                if ($torrent) {
                    header('Location: ' . $torrent->location());
                } else {
                    header("Location: log.php?search=Torrent+" . $_GET['torrentid']);
                }
            } else {
                include_once 'browse.php';
            }
            break;
    }
} else {
    $manager = new \Gazelle\Manager\TGroup();
    if (!empty($_GET['id'])) {
        include_once 'details.php';
    } elseif (isset($_GET['torrentid'])) {
        $torrentId = (int)$_GET['torrentid'];
        $tgroup = $manager->findByTorrentId($torrentId);
        if ($tgroup) {
            header("Location: torrents.php?id={$tgroup->id()}&torrentid={$torrentId}#torrent{$torrentId}");
        } else {
            header("Location: log.php?search=Torrent+$torrentId");
        }
    } elseif (!empty($_GET['type'])) {
        include_once 'user.php';
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
        include_once 'browse.php';
    }
}
