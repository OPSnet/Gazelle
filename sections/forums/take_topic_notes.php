<?php
authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$threadId = (int)$_POST['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
$body = trim($_POST['threadid'] ?? null);
if (!strlen($body)) {
    error("Thread note cannot be empty");
}

$forum->addThreadNote($threadId, $Viewer->id(), $body);

header("Location: forums.php?action=viewthread&threadid=$threadId#thread_notes");
