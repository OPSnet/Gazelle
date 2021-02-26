<?php

$postId = (int)$_GET['post'];
try {
    $forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
} catch (Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
if (!(new Gazelle\User($LoggedUser['ID']))->readAccess($forum)) {
    error(403);
}
echo trim(display_str($forum->postBody($postId)));
