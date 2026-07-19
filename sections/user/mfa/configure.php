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
$mfa = $user->MFA();
if ($mfa->enabled()) {
    Error400::error('MFA is already configured');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$valid = true;
if (isset($_SESSION['private_key'], $_POST['mfa'])) {
    $secret = $_SESSION['private_key'];
    if ($mfa->verifyCode($secret, trim($_POST['mfa']))) {
        $recoveryKeys = $user->MFA()->create(new Manager\UserToken(), $_SESSION['private_key'], $Viewer);
        if (!$recoveryKeys) {
            Error400::error('failed to create MFA');
        }
        unset($_SESSION['private_key']);
        session_write_close();

        echo $Twig->render('user/mfa/complete.twig', [
            'keys' => $recoveryKeys,
        ]);
        exit;
    }
    session_abort();
    $valid = false;
} else {
    authorize();
    $_SESSION['private_key'] = $secret = $mfa->generateSessionSecret();
    session_write_close();
}

echo $Twig->render('user/mfa/configure.twig', [
    'valid'  => $valid,
    'qrcode' => $mfa->generateQrCode(secret: $secret, logo: QRCODE_LOGO),
    'secret' => $secret,
]);
