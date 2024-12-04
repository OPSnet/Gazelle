<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$user = (new Gazelle\Manager\User())->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
if ($user->MFA()->enabled()) {
    error($Viewer->permitted('users_edit_password') ? '2FA is already configured' : 404);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}
if (empty($_SESSION['private_key'])) {
    error(404);
}

$recoveryKeys = $user->MFA()->create(new Gazelle\Manager\UserToken(), $_SESSION['private_key'], $Viewer);
if (!$recoveryKeys) {
    error('failed to create 2FA');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['private_key']);
session_write_close();

echo $Twig->render('user/2fa/complete.twig', [
    'keys' => $recoveryKeys,
]);
