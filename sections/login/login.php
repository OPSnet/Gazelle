<?php
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (isset($Viewer)) {
    header("Location: /index.php");
    exit;
}

$login = new Login();
$watch = new LoginWatch($login->requestContext()->remoteAddr());

if (!empty($_POST['username']) && !empty($_POST['password'])) {
    $user = $login->login(
        username:   $_POST['username'],
        password:   $_POST['password'],
        watch:      $watch,
        mfaToken:   $_POST['mfa'] ?? '',
        persistent: isset($_POST['keeplogged']),
    );

    if ($user) {
        if ($user->isDisabled()) {
            if (FEATURE_EMAIL_REENABLE) {
                setcookie('username', urlencode($user->username()), [
                    'expires'  => time() + 60 * 60,
                    'path'     => '/',
                    'secure'   => SECURE_COOKIE,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            }
            header("Location: login.php?action=disabled");
            exit;
        }

        if ($user->isEnabled()) {
            if (!Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
                $user->addStaffNote("login prevented because of weak/compromised password")->modify();
                $user->session()->logoutEverywhere();
                echo $Twig->render('login/weak-password.twig');
                exit;
            }
            $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '[no-useragent]';
            $context = new RequestContext(
                $_SERVER['SCRIPT_NAME'],
                $_SERVER['REMOTE_ADDR'],
                $useragent,
            );
            if ($user->permitted('site_disable_ip_history')) {
                $context->anonymize();
            }
            $session = new User\Session($user);
            $current = $session->create([
                'keep-logged' => $login->persistent() ? '1' : '0',
                'browser'     => $context->ua(),
                'ipaddr'      => $context->remoteAddr(),
                'useragent'   => $context->useragent(),
            ]);

            new SessionCookie(SessionCookie::encode($user, $current['SessionID']))
                ->emit((int)$login->persistent() * (time() + 60 * 60 * 24 * 90));
            header("Location: index.php");
            exit;
        }
    }
}

echo $Twig->render('login/login.twig', [
    'delta'    => (int)$watch->bannedEpoch() - time(),
    'error'    => $login->error(),
    'ip_addr'  => $login->requestContext()->remoteAddr(),
    'tor_node' => new Manager\Tor()->isExitNode(
        $login->requestContext()->remoteAddr()
    ),
    'watch'    => $watch,
    'keep_logged' => isset($_POST['keeplogged']),
]);
