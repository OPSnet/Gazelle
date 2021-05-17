<?php

authorize();

[$artistId, $name] = $DB->row("
    SELECT ArtistID, concat(Name, IF(VanityHouse = 0, '', ' [Vanity House]')) as Name
    FROM artists_group
    WHERE ArtistID = ?
    ", (int)$_POST['artistid']
);
if (!$artistId) {
    error(404);
}

$forum = new \Gazelle\Forum(EDITING_FORUM_ID);
$threadId = $forum->addThread(
    SYSTEM_USER_ID,
    "Editing request — Artist: $name",
    $Twig->render('forum/request-edit.twig', [
        'username' => $LoggedUser['Username'],
        'url'      => 'artist.php?id=' . $artistId,
        'name'     => $name,
        'details'  => trim($_POST['edit_details']),
    ])
);

header("Location: forums.php?action=viewthread&threadid={$threadId}");
