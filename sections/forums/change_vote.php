<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$poll = new Manager\ForumPoll()->findById((int)($_GET['threadid'] ?? 0));

if (is_null($poll)) {
    Error404::error();
}
if (!$Viewer->permitted('site_moderate_forums') && !$poll->hasRevealVotes()) {
    Error403::error();
}

if (!isset($_GET['vote']) || !is_number($_GET['vote'])) {
    Error400::error();
}
$poll->modifyVote($Viewer, (int)$_GET['vote']);

header("Location: " . $poll->location());
