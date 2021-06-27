<?php
authorize();

$vote = (int)$_GET['vote'];
if (!$vote) {
    error(404);
}

$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
if (!check_perms('site_moderate_forums') && !in_array($forum->id(), $ForumsRevealVoters)) {
    error(403);
}

$forum->modifyVote($Viewer->id(), $threadId, $vote);

header("Location: forums.php?action=viewthread&threadid=$threadId");
