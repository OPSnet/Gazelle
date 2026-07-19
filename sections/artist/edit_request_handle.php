<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$artist = new Manager\Artist()->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    Error404::error();
}
authorize();

$thread = new Manager\ForumThread()->create(
    forum: new Forum(EDITING_FORUM_ID),
    user:  new User(SYSTEM_USER_ID),
    title: "Editing request – Artist: " . $artist->name(),
    body:  $Twig->render('forum/edit-request-body.twig', [
        'link'    => '[artist]' . $artist->name() . '[/artist]',
        'details' => trim($_POST['edit_details']),
        'viewer'  => $Viewer,
    ])
);

header("Location: {$thread->location()}");
