<?php

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)($_POST['id'] ?? 0));
if (!$tgroup) {
    error(404);
}
authorize();

$threadId = (new Gazelle\Forum(EDITING_FORUM_ID))->addThread(
    SYSTEM_USER_ID,
    "Editing request \xE2\x80\x93 Torrent Group: " . $tgroup->name(),
    $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[torrent]' . $tgroup->id() . '[/torrent]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ])
);

header("Location: forums.php?action=viewthread&threadid={$threadId}");
