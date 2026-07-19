<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_forums')) {
    Error403::error();
}

if (!isset($_REQUEST['userid'])) {
    $user = $Viewer;
} else {
    $user = new Manager\User()->find((int)$_REQUEST['userid']);
    if (is_null($user)) {
        Error404::error();
    }
}

echo $Twig->render('admin/forum-transition.twig', [
    'class_list' => new Manager\User()->classList(),
    'forum_list' => new Manager\Forum()->forumList(),
    'user_list'  => new Manager\ForumTransition()->userTransitionList($user),
    'user'       => $user,
    'viewer'     => $Viewer,
]);
