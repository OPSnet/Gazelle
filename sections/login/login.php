<?php
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (isset($Viewer)) {
    header("Location: /index.php");
    exit;
}

$login = new Gazelle\Login();
$watch = new Gazelle\LoginWatch($login->requestContext()->remoteAddr());

if (!empty($_POST['username']) && !empty($_POST['password'])) {
    $user = $login->login(
        username:   $_POST['username'],
        password:   $_POST['password'],
        watch:      $watch,
        twofa:      $_POST['twofa'] ?? '',
        persistent: $_POST['keeplogged'] ?? false,
    );

    if ($user) {
        if ($user->isDisabled()) {
            if (FEATURE_EMAIL_REENABLE) {
                setcookie('username', urlencode($user->username()), [
                    'expires'  => time() + 60 * 60,
                    'path'     => '/',
                    'secure'   => !DEBUG_MODE,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            header("Location: login.php?action=disabled");
            exit;
        }

        if ($user->isEnabled()) {
            if (!\Gazelle\Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
                $user->addStaffNote("login prevented because of weak/compromised password")->modify();
                $user->logoutEverywhere();
                echo $Twig->render('login/weak-password.twig');
                exit;
            }
            $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '[no-useragent]';
            $context = new Gazelle\BaseRequestContext(
                $_SERVER['SCRIPT_NAME'],
                $_SERVER['REMOTE_ADDR'],
                $useragent,
            );
            if ($user->permitted('site_disable_ip_history')) {
                $context->anonymize();
            }
            $session = new Gazelle\User\Session($user);
            $current = $session->create([
                'keep-logged' => $login->persistent() ? '1' : '0',
                'browser'     => $context->ua(),
                'ipaddr'      => $context->remoteAddr(),
                'useragent'   => $useragent,
            ]);
            setcookie('session', $session->cookie($current['SessionID']), [
                'expires'  => (int)$login->persistent() * (time() + 60 * 60 * 24 * 90),
                'path'     => '/',
                'secure'   => !DEBUG_MODE,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header("Location: index.php");
            exit;
        }
    }
}

echo $Twig->render('login/login.twig', [
    'delta'    => $watch->bannedEpoch() - time(),
    'error'    => $login->error(),
    'ip_addr'  => $login->requestContext()->remoteAddr(),
    'tor_node' => (new Gazelle\Manager\Tor())->isExitNode(
        $login->requestContext()->remoteAddr()
    ),
    'watch'    => $watch,
]);
