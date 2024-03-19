<?php

if (isset($Viewer)) {
    header("Location: /index.php");
    exit;
}

$watch = new Gazelle\LoginWatch($_SERVER['REMOTE_ADDR']);
$login = new Gazelle\Login();

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
            $browser = parse_user_agent($_SERVER['HTTP_USER_AGENT']);
            if ($user->permitted('site_disable_ip_history')) {
                $ipaddr = '127.0.0.1';
                $browser['BrowserVersion'] = null;
                $browser['OperatingSystemVersion'] = null;
                $full_ua = 'staff-browser';
            } else {
                $ipaddr = $_SERVER['REMOTE_ADDR'];
                $full_ua = $_SERVER['HTTP_USER_AGENT'];
            }
            $session = new Gazelle\User\Session($user);
            $current = $session->create([
                'keep-logged' => $login->persistent() ? '1' : '0',
                'browser'     => $browser,
                'ipaddr'      => $ipaddr,
                'useragent'   => $full_ua,
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
    'ip_addr'  => $_SERVER['REMOTE_ADDR'],
    'tor_node' => (new Gazelle\Manager\Tor())->isExitNode($_SERVER['REMOTE_ADDR']),
    'watch'    => $watch,
]);
