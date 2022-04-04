<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
if (!$thread->hasRevealVotes()) {
    error(403);
}
$thread->addPollAnswer(trim($_POST['new_option']));

header("Location: " . $thread->location());
