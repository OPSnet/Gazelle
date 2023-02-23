<?php

$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

echo Gazelle\DB::DB()->scalar("
    SELECT Body FROM comments WHERE ID = ?
    ", $postId
);
