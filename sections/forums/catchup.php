<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

if (($_GET['forumid'] ?? '') == 'all') {
    $Viewer->updateCatchup();
    header('Location: forums.php');
    exit;
}

$forum = (new Gazelle\Manager\Forum())->findById((int)($_GET['forumid'] ?? 0));
if (is_null($forum)) {
    error(404);
}

$forum->userCatchup($Viewer);
header('Location: ' . $forum->location());
