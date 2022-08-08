<?php

$post = (new Gazelle\Manager\ForumPost)->findById((int)($_GET['postid'] ?? 0));
if (is_null($post)) {
    json_die("failure", "empty postid");
} elseif (!$Viewer->readAccess($post->thread()->forum())) {
    json_die("failure", "assholes");
}

json_print("success", ["body" => nl2br($post->body())]);
