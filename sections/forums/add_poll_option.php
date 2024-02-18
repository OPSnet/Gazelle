<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$poll = (new Gazelle\Manager\ForumPoll())->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(404);
}
if (!$poll->hasRevealVotes()) {
    error(403);
}
$poll->addAnswer(trim($_POST['new_option']));

header("Location: " . $poll->location());
