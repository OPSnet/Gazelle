<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}
authorize();

$poll = new Manager\ForumPoll()->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    Error404::error();
}
if (!$poll->hasRevealVotes()) {
    Error403::error();
}
$poll->addAnswer(trim($_POST['new_option']));

header("Location: " . $poll->location());
