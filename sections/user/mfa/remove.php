<?php

/**
 * @phpstan-var \Gazelle\User $user
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!isset($user)) {
    Error500::error();
}
if (!$user->MFA()->enabled()) {
    Error400::error('No MFA configured');
}

// Remove MFA. Users have to enter their password, moderators skip this step.
if ($Viewer->permitted('users_edit_password')) {
    authorize();
} else {
    if ($user->id !== $Viewer->id) {
        Error403::error();
    } elseif (!isset($_POST['password'])) {
        echo $Twig->render('user/mfa/remove.twig', ['bad' => false]);
        exit;
    } elseif (!$user->validatePassword($_POST['password'])) {
        echo $Twig->render('user/mfa/remove.twig', ['bad' => true]);
        exit;
    }
}
$user->MFA()->remove();

header("Location: {$user->location()}");
