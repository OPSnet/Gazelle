<?php

$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

header('Content-type: text/plain');
echo Gazelle\DB::DB()->scalar("
    SELECT Body FROM comments WHERE ID = ?
    ", $postId
);
