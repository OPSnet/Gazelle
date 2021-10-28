<?php

$artist = (new Gazelle\Manager\Artist)->findById((int)($_POST['artistid'] ?? 0));
if (!$artist) {
    error(404);
}
authorize();

$threadId = (new Gazelle\Forum(EDITING_FORUM_ID))->addThread(
    SYSTEM_USER_ID,
    "Editing request \xE2\x80\x93 Artist: " . $artist->name(),
    $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[artist]' . $artist->name() . '[/artist]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ])
);

header("Location: forums.php?action=viewthread&threadid={$threadId}");
