<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$forum = new Manager\Forum()->findById((int)($_GET['forumid'] ?? 0));
if (!$forum) {
    Error404::error();
}
if (!$Viewer->writeAccess($forum) || !$Viewer->createAccess($forum)) {
    Error403::error();
}

echo $Twig->render('forum/new-thread.twig', [
    'forum'     => $forum,
    'textarea'  => new Util\Textarea('body', '', 90, 8),
    'viewer'    => $Viewer,
]);
