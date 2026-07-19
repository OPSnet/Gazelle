<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$tgroup = new Manager\TGroup()->findById((int)($_POST['id'] ?? 0));
if (!$tgroup) {
    Error404::error();
}
authorize();

$thread = new Manager\ForumThread()->create(
    forum: new Forum(EDITING_FORUM_ID),
    user:  new User(SYSTEM_USER_ID),
    title: "Editing request \xE2\x80\x93 Torrent Group: " . $tgroup->name(),
    body:  $Twig->render('forum/edit-request-body.twig', [
        'link'    => "[torrent]{$tgroup->id}[/torrent]",
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ]),
);

header("Location: {$thread->location()}");
