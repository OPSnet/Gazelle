<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (($_GET['forumid'] ?? '') == 'all') {
    $Viewer->updateCatchup();
    header('Location: forums.php');
    return;
}

$forum = new Manager\Forum()->findById((int)($_GET['forumid'] ?? 0));
if (is_null($forum)) {
    Error404::error();
}

$forum->userCatchup($Viewer);
header('Location: ' . $forum->location());
