<?php

declare(strict_types=1);

namespace Gazelle;

$includeFile = match ($_REQUEST['action'] ?? '') {
    'add_alias'          => 'add_alias.php',
    'add_cover_art'      => 'add_cover_art.php',
    'add_tag'            => '../ajax/torrent_tag_add.php',
    'autocomplete_tags'  => 'autocomplete_tags.php',
    'changecategory'     => 'edit_category_handle.php',
    'collector'          => 'collector.php',  // NB: called from better.php
    'delete_alias'       => 'delete_alias.php',
    'delete'             => 'delete.php',
    'download'           => 'download.php',
    'downloadlist'       => 'downloadlist.php',
    'edit'               => 'edit.php',
    'editgroup'          => 'edit_group.php',
    'editgroupid'        => 'new_groupid.php',
    'editlog'            => 'edit_log.php',
    'editrequest'        => 'edit_request.php',
    'filelist'           => 'filelist.php',
    'grouplog'           => 'grouplog.php',
    'history'            => 'history.php',
    'manage_artists'     => 'manage_artists.php',
    'masspm'             => 'masspm.php',
    'newgroup'           => 'new_group_handle.php',
    'notify_catchup',
    'notify_catchup_filter',
    'notify_clear',
    'notify_clear_filter',
    'notify_clear_item',
    'notify_clear_items' => 'notify_actions.php',
    'notify'             => 'notify.php',
    'peerlist'           => 'peerlist.php',
    'redownload'         => 'redownload.php',
    'regen_filelist'     => 'regen.php',
    'deletelog', // legacy name
    'removelog'          => 'remove_log.php',
    'removelogs'         => 'remove_logs.php',
    'rename'             => 'rename.php',
    'rescore_log'        => 'rescore_log.php',
    'reseed'             => 'reseed.php',
    'snatchlist'         => 'snatchlist.php',
    'takedelete'         => 'delete_handle.php',
    'takeedit'           => 'edit_handle.php',
    'take_editlog'       => 'edit_log_handle.php',
    'takeeditrequest'    => 'edit_request_handle.php',
    'takemasspm'         => 'masspm_handle.php',
    'revert',
    'tgroup-edit'        => 'edit_group_handle.php',
    'tgroup-merge'       => 'merge.php',
    'viewlog'            => 'log_ajax.php',
    'vote_tag'           => 'vote_tag.php',
    default              => null,
};

if ($includeFile) {
    require __DIR__ . '/' . $includeFile;
} elseif (isset($_GET['id'])) {
    require __DIR__ . '/details.php';
} elseif (isset($_GET['torrentid'])) {
    $torrent = new Manager\Torrent()->findById((int)$_GET['torrentid']);
    if ($torrent) {
        header('Location: ' . $torrent->location());
    } else {
        header("Location: log.php?search=Torrent+" . (int)$_GET['torrentid']);
    }
} elseif (isset($_GET['type'])) {
    require __DIR__ . '/user.php';
} elseif (isset($_GET['groupname'])) {
    $idList = new Manager\TGroup()->findByGroupName(trim($_GET['groupname']));
    if (count($idList) === 1) {
        header("Location: torrents.php?id=$idList[0]");
    } else {
        require __DIR__ . '/browse.php';
    }
} else {
    require __DIR__ . '/browse.php';
}
