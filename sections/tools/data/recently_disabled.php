<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_disable_users')) {
    Error403::error();
}

echo $Twig->render('admin/recently_disabled.twig', [
    'user_reason_list' => Util\DisabledUserHistory::get(),
]);
