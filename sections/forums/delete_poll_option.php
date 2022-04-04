<?php

authorize();

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
if (!$thread->hasRevealVotes()) {
    error(403);
}

$vote = (int)$_GET['vote'];
if (!$vote) {
    error(404);
}
$thread->removePollAnswer($vote);

header("Location: " . $thread->location());
