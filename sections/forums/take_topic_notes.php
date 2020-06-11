<?php
authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$threadId = (int)$_POST['topicid'];
if ($threadId < 1) {
    error(404);
}
$body = trim($_POST['topicid'] ?? null);
if (!strlen($body)) {
    error("Thread note cannot be empty");
}

$forum = new \Gazelle\Forum;
$forum->addThreadNote($threadId, $LoggedUser['ID'], $body);

header("Location: forums.php?action=viewthread&threadid=$threadId#thread_notes");
