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

$comment = new Manager\Comment()->findById((int)($_POST['postid'] ?? 0));
if (is_null($comment)) {
    Error404::error();
}

echo $Twig->render('comment/warn.twig', [
    'comment' => $comment,
    'viewer'  => $Viewer,
]);
