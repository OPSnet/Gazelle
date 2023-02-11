<?php

$enabler = null;
if (isset($_POST['username'])) {
    $user = (new Gazelle\Manager\User)->findByUsername(trim($_POST['username']));
    if ($user) {
        $enabler = (new Gazelle\Manager\AutoEnable)->create($user, $_POST['email']);
        if ($enabler) {
            setcookie('username', '', [
                'expires'  => time() + 60 * 60,
                'path'     => '/',
                'secure'   => !DEBUG_MODE, /** @phpstan-ignore-line */
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

echo $Twig->render('login/disabled.twig', [
    'username' => $_COOKIE['username'] ?? $_POST['username'] ?? '',
    'auto'     => (FEATURE_EMAIL_REENABLE /** @phpstan-ignore-line */ && ($_POST['email'] ?? '') != ''),
    'enabler'  => $enabler,
]);
