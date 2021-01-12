<?php
authorize();

if (!check_perms('forums_polls_moderate')) {
    error(403,true);
}

$threadId = (int)$_POST['topicid'];
if (!$threadId) {
    error(0,true);
}

$forum = new Gazelle\Forum(0);
$forum->setForumFromThread($threadId);
$forum->moderatePoll($threadId, isset($_POST['feature']), isset($_POST['close']));

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "forums.php?action=viewthread&threadid={$threadId}"));
