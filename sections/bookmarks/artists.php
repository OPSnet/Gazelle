<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
    if ($user->id != $Viewer->id && !$Viewer->permitted('users_override_paranoia')) {
        Error403::error();
    }
}

echo $Twig->render('bookmark/artist.twig', [
    'list'   => new User\Bookmark($user)->artistList(),
    'user'   => $user,
    'viewer' => $Viewer,
]);
