<?php

$comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    error(404);
}
header('Location: ' . $comment->location());
