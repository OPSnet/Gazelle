<?php
enforce_login();

$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

echo $DB->scalar("
    SELECT Body FROM comments WHERE ID = ?
    ", $postId
);
