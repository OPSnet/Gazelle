<?php

$postId = (int)$_GET['post'];
$forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
if (is_null($forum)) {
    error(404);
}
if (!(new Gazelle\User($LoggedUser['ID']))->readAccess($forum)) {
    error(403);
}
echo trim(display_str($forum->postBody($postId)));
