<?php
authorize();

// Make sure they are moderators
if (!check_perms('site_admin_forums')) {
    error(403);
}

$postId = (int)$_GET['postid'];
if ($postId < 1) {
    error(0);
}

$forum = new \Gazelle\Forum();
if (!$forum->removePost($postId)) {
    error(0);
}
