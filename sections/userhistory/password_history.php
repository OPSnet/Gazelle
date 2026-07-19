<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_keys')) {
    Error403::error();
}

$user = new Manager\User()->findById((int)$_GET['userid']);
if (is_null($user)) {
    Error404::error();
}

echo $Twig->render('user/password-history.twig', [
    'user' => $user,
]);
