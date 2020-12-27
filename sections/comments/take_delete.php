<?php

authorize();
if (!check_perms('site_moderate_forums')) {
    error(403);
}

try {
    $comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
} catch (\Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
$comment->remove();
