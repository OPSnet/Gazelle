<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!$forum->hasRevealVotes()) {
    error(403);
}
$forum->addPollAnswer($threadId, trim($_POST['new_option']));

header("Location: forums.php?action=viewthread&threadid=$threadId");
