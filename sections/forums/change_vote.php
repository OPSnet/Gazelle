<?php

authorize();

$poll = (new Gazelle\Manager\ForumPoll)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($poll)) {
    error(404);
}
if (!$Viewer->permitted('site_moderate_forums') && !$poll->hasRevealVotes()) {
    error(403);
}

$vote = (int)$_GET['vote'];
if (!$vote) {
    error(404);
}
$poll->modifyVote($Viewer, $vote);

header("Location: " . $poll->location());
