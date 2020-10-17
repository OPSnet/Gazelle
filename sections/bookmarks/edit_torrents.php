<?php

if (empty($_GET['userid'])) {
    $UserID = $LoggedUser['ID'];
} else {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $UserID = (int)$_GET['userid'];
    if (!$UserID) {
        error(404);
    }
}

$EditType = isset($_GET['type']) ? $_GET['type'] : 'torrents';
[, $CollageDataList, $TorrentList] = Users::get_bookmarks($UserID); // TODO: $TorrentList might not have the correct order, use the $GroupIDs instead

View::show_header('Organize Bookmarks', 'browse,jquery-ui,jquery.tablesorter,sort');

$TT = new mass_user_torrents_table_view($TorrentList, $CollageDataList, $EditType, 'Organize Torrent Bookmarks');
$TT->render_all();

View::show_footer();
