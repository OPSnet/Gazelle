<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_POST['id'] ?? 0));
if (!$tgroup) {
    error(404);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread())->create(
    forum: new Gazelle\Forum(EDITING_FORUM_ID),
    user:  new Gazelle\User(SYSTEM_USER_ID),
    title: "Editing request \xE2\x80\x93 Torrent Group: " . $tgroup->name(),
    body:  $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[torrent]' . $tgroup->id() . '[/torrent]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ]),
);

header("Location: {$thread->location()}");
