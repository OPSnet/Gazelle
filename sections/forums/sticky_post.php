<?php

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

$thread->pinPost($Viewer->id(), $postId, empty($_GET['remove']));

header('Location: ' . $thread->location());
