<?php

if (!check_perms('users_warn')) {
    error(404);
}

$postId = (int)$_POST['postid'];
if (!$postId) {
    error(404);
}
$user = (new Gazelle\Manager\User)->findById((int)$_POST['userid']);
if (is_null($user)) {
    error(404);
}

[$body, $forumId] = $DB->row("
    SELECT p.Body, t.ForumID
    FROM forums_posts AS p
    INNER JOIN forums_topics AS t ON (p.TopicID = t.ID)
    WHERE p.ID = ?
    ", $postId
);

View::show_header('Warn User');
echo G::$Twig->render('forum/warn.twig', [
    'auth'     => $LoggedUser['AuthKey'],
    'body'     => $body,
    'forum_id' => $forumId,
    'post_id'  => $postId,
    'user'     => $user,
]);
View::show_footer();
