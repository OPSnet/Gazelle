<?php
authorize();
if (!check_perms('site_moderate_forums')) {
    error(404);
}

$threadId = (int)$_GET['threadid'];
$option = (int)$_GET['vote'];

if ($threadId < 1 || $option < 1) {
    error(404);
}

if (!check_perms('site_moderate_forums')) {
    $forumId = $DB->scalar("
        SELECT forumId
        FROM forums_topics
        WHERE ID = ?
        ", $threadId
    );
    if (!in_array($forumId, $ForumsRevealVoters)) {
        error(403);
    }
}
$forum = new \Gazelle\Forum($forumId);
$forum->removePollAnswer($threadId, $option);

header("Location: forums.php?action=viewthread&threadid=$threadId");
