<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
authorize();

$comment = (new Gazelle\Manager\Comment())->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    error(404);
}
$comment->remove();
