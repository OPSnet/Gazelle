<?php

authorize();

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$poll = (new Gazelle\Manager\ForumPoll)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(404);
}
if (!$poll->hasRevealVotes()) {
    error(403);
}

$vote = (int)$_GET['vote'];
if (!$vote) {
    error(404);
}
$poll->removeAnswer($vote);

header("Location: " . $poll->location());
