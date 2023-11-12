<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    error("Thread note cannot be empty");
}

$thread->addThreadNote($Viewer->id(), $body);

header("Location: {$thread->location()}#thread_notes");
