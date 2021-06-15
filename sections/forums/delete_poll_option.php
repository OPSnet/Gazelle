<?php

authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$option = (int)$_GET['vote'];
if (!$option) {
    error(404);
}
$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!$forum->hasRevealVotes()) {
    error(403);
}
$forum->removePollAnswer($threadId, $option);

header("Location: forums.php?action=viewthread&threadid=$threadId");
