<?php

if (!check_perms('users_warn')) {
    error(403);
}
$postId = (int)$_POST['postid'];
if (!$postId) {
    error(404);
}

[$body, $userId] = $DB->row("
    SELECT Body, AuthorID FROM comments WHERE ID = ?
    ", $postId
);
if (is_null($body)) {
    error(404);
}

View::show_header('Warn User');

echo $Twig->render('comment/warn.twig', [
    'body'     => $body,
    'post_id'  => $postId,
    'user'     => new Gazelle\User($userId),
]);

View::show_footer();
