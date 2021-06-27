<?php

authorize();

$groupId = (int)$_POST['groupid'];
if ($groupId < 1) {
    error(404);
}

$torrentCache = get_group_info($groupId, $RevisionID);

list(, , $groupId, $groupName, $year, , , , , , $VH) = array_values($torrentCache[0]);

$artists = Artists::get_artist($groupId);
if ($artists) {
    $groupName = Artists::display_artists($artists, false, true, false) . $groupName;
}
if ($year > 0) {
    $groupName .= " [$year]";
}
if ($VH) {
    $groupName .= ' [Vanity House]';
}

$forum = new \Gazelle\Forum(EDITING_FORUM_ID);
$threadId = $forum->addThread(
    SYSTEM_USER_ID,
    "Editing request – Torrent Group: $groupName",
    $Twig->render('forum/request-edit.twig', [
        'username' => $Viewer->username(),
        'url'      => 'torrents.php?id=' . $groupId,
        'name'     => $name,
        'details'  => trim($_POST['edit_details']),
    ])
);

header("Location: forums.php?action=viewthread&threadid={$threadId}");
