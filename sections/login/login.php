<?php

if (isset($Viewer)) {
    header("Location: /index.php");
    exit;
}

$watch = new Gazelle\LoginWatch($_SERVER['REMOTE_ADDR']);
$login = new Gazelle\Login;

if (isset($_POST['username'])) {
    $user = $login->login(
        username:   $_POST['username'],
        password:   $_POST['password'] ?? '',
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
                    'httponly' => DEBUG_MODE,
                    'samesite' => 'Lax',
                ]);
            }
            header("Location: login.php?action=disabled");
            exit;
        }

        if ($user->isEnabled()) {
            $browser = parse_user_agent();
            $session = new Gazelle\User\Session($user);
            $current = $session->create([
                'keep-logged' => $login->persistent() ? '1' : '0',
                'browser'     => $browser['Browser'],
                'os'          => $browser['OperatingSystem'],
                'ipaddr'      => $user->permitted('site_disable_ip_history') ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'],
                'useragent'   => $user->permitted('site_disable_ip_history') ? FAKE_USERAGENT : $_SERVER['HTTP_USER_AGENT'],
            ]);
            setcookie('session', $session->cookie($current['SessionID']), [
                'expires'  => (int)$login->persistent() * (time() + 60 * 60 * 24 * 90),
                'path'     => '/',
                'secure'   => !DEBUG_MODE,
                'httponly' => DEBUG_MODE,
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
    'tor_node' => BLOCK_TOR && (new Gazelle\Manager\Tor)->isExitNode($_SERVER['REMOTE_ADDR']),
    'watch'    => $watch,
]);
