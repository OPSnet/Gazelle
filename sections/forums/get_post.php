<?php

$postId = (int)$_GET['post'];
try {
    $forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
} catch (Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
if (!Forums::check_forumperm($forum->id())) {
    error(403);
}
echo trim(display_str($forum->postBody($postId)));
