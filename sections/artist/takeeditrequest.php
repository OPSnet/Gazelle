<?php

$artist = (new Gazelle\Manager\Artist)->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->create(
    forumId: EDITING_FORUM_ID,
    userId:  SYSTEM_USER_ID,
    title:   "Editing request \xE2\x80\x93 Artist: " . $artist->name(),
    body:    $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[artist]' . $artist->name() . '[/artist]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ])
);

header("Location: {$thread->location()}");
