<?php
authorize();

$threadId = (int)$_POST['threadid'];
if ($threadId < 1) {
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
$forum->addPollAnswer($threadId, trim($_POST['new_option']));

header("Location: forums.php?action=viewthread&threadid=$threadId");
