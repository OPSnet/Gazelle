<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}

$poll = new Manager\ForumPoll()->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    Error404::error();
}
if (!$poll->hasRevealVotes()) {
    Error403::error();
}

$vote = (int)$_GET['vote'];
if (!$vote) {
    Error404::error();
}
$poll->removeAnswer($vote);

header("Location: {$poll->location()}");
