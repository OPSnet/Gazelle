<?php

$artist = (new Gazelle\Manager\Artist)->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->create(
    forum: new Gazelle\Forum(EDITING_FORUM_ID),
    user:  new Gazelle\User(SYSTEM_USER_ID),
    title: "Editing request – Artist: " . $artist->name(),
    body:  $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[artist]' . $artist->name() . '[/artist]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ])
);

header("Location: {$thread->location()}");
