<?php

$postId = (int)$_GET['postid'];
$forum = $forumMan->findByPostId($postId);
if (is_null($forum)) {
    json_die("failure", "empty postid");
} elseif (!$Viewer->readAccess($forum)) {
    json_die("failure", "assholes");
}

json_print("success", ["body" => nl2br($forum->postBody($postId))]);
