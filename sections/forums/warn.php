<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_warn')) {
    Error403::error();
}
authorize();

$post = new Manager\ForumPost()->findById((int)($_POST['postid'] ?? 0));
if (is_null($post)) {
    Error404::error();
}
$user = new Manager\User()->findById((int)($_POST['userid'] ?? 0));
if (is_null($user)) {
    Error404::error();
}

echo $Twig->render('forum/warn.twig', [
    'post'   => $post,
    'user'   => $user,
    'viewer' => $Viewer,
]);
