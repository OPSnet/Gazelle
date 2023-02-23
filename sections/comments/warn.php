<?php

if (!$Viewer->permitted('users_warn')) {
    error(403);
}
$postId = (int)$_POST['postid'];
if (!$postId) {
    error(404);
}

[$body, $userId] = Gazelle\DB::DB()->row("
    SELECT Body, AuthorID FROM comments WHERE ID = ?
    ", $postId
);
if (is_null($body)) {
    error(404);
}

echo $Twig->render('comment/warn.twig', [
    'body'     => $body,
    'post_id'  => $postId,
    'user'     => new Gazelle\User($userId),
]);
