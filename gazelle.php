<?php

use Gazelle\Util\Crypto;
use Gazelle\Util\Time;

// 1. Basic sanity checks and initialization

if (PHP_VERSION_ID < 80201) {
    die("Gazelle (Orpheus fork) requires at least PHP version 8.2.1");
}
foreach (['memcached', 'mysqli'] as $e) {
    if (!extension_loaded($e)) {
        die("$e extension not loaded");
    }
}
date_default_timezone_set('UTC');

$PathInfo = pathinfo($_SERVER['SCRIPT_NAME']);
$Document = $PathInfo['filename'];

if ($PathInfo['dirname'] !== '/') { /** @phpstan-ignore-line */
    exit;
} elseif (in_array($Document, ['announce', 'scrape']) || (isset($_REQUEST['info_hash']) && isset($_REQUEST['peer_id']))) {
    die("d14:failure reason40:Invalid .torrent, try downloading again.e");
}

// 2. Start the engine

require_once(__DIR__ . '/lib/bootstrap.php');
global $Cache, $Debug, $Twig;

// Get the user's actual IP address if they're proxied.
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])
    && proxyCheck($_SERVER['REMOTE_ADDR'])
    && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = '[no-useragent]';
}

// 3. Do we have a viewer?

$SessionID = false;
$Viewer    = null;
$ipv4Man   = new Gazelle\Manager\IPv4();
$userMan   = new Gazelle\Manager\User();
Gazelle\Util\Twig::setUserMan($userMan);

// Authorization header only makes sense for the ajax endpoint
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $Document === 'ajax') {
    if ($ipv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
        header('Content-type: application/json');
        json_die('failure', 'your ip address has been banned');
    }
    [$success, $result] = $userMan->findByAuthorization($ipv4Man, $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REMOTE_ADDR']);
    if ($success) {
        $Viewer = $result;
        define('AUTHED_BY_TOKEN', true);
    } else {
        header('Content-type: application/json');
        json_die('failure', $result);
    }
} elseif (isset($_COOKIE['session'])) {
    $forceLogout = function (): never {
        setcookie('session', '', [
            'expires'  => time() - 60 * 60 * 24 * 90,
            'path'     => '/',
            'secure'   => !DEBUG_MODE,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: login.php');
        exit;
    };
    $cookieData = Crypto::decrypt($_COOKIE['session'], ENCKEY);
    if ($cookieData === false) {
        $forceLogout();
    }
    [$SessionID, $userId] = explode('|~|', $cookieData);
    $Viewer = $userMan->findById((int)$userId);
    if (is_null($Viewer)) {
        $forceLogout();
    }
    if ($Viewer->isDisabled() && !in_array($Document, ['index', 'login'])) {
        $Viewer->logoutEverywhere();
        $forceLogout();
    }
    $session = new Gazelle\User\Session($Viewer);
    if (!$session->valid($SessionID)) {
        $Viewer->logout($SessionID);
        $forceLogout();
    }
    $browser = parse_user_agent($_SERVER['HTTP_USER_AGENT']);
    if ($Viewer->permitted('site_disable_ip_history')) {
        $ipaddr = '127.0.0.1';
        $browser['BrowserVersion'] = null;
        $browser['OperatingSystemVersion'] = null;
    } else {
        $ipaddr = $_SERVER['REMOTE_ADDR'];
    }
    $session->refresh($SessionID, $ipaddr, $browser);
    unset($ipaddr, $browser, $session, $userId, $cookieData, $forceLogout);
} elseif ($Document === 'torrents' && ($_REQUEST['action'] ?? '') == 'download' && isset($_REQUEST['torrent_pass'])) {
    $Viewer = $userMan->findByAnnounceKey($_REQUEST['torrent_pass']);
    if (is_null($Viewer) || $Viewer->isDisabled() || $Viewer->isLocked()) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
} elseif (!in_array($Document, ['enable', 'index', 'login', 'recovery', 'register'])) {
    if (
        // Ocelot is allowed
        !($Document === 'tools' && ($_GET['action'] ?? '') === 'ocelot' && ($_GET['key'] ?? '') === TRACKER_SECRET)
    ) {
        // but for everything else, we need a $Viewer
        header('Location: login.php');
        exit;
    }
}

// 4. We have a viewer (or this is a login or registration attempt)

if ($Viewer) {
    if ($Viewer->hasAttr('admin-error-reporting')) {
        error_reporting(E_ALL);
    }

    // Because we <3 our staff
    if ($Viewer->permitted('site_disable_ip_history')) {
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT']      = 'staff-browser';
    }
    if ($Viewer->ipaddr() != $_SERVER['REMOTE_ADDR'] && !$Viewer->permitted('site_disable_ip_history')) {
        if ($ipv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
            error('Your IP address has been banned.');
        }
        $ipv4Man->register($Viewer, $_SERVER['REMOTE_ADDR']);
    }
    if ($Viewer->isLocked() && !in_array($Document, ['staffpm', 'ajax', 'locked', 'logout', 'login'])) {
        $Document = 'locked';
    }

    // To proxify images (or not), or e.g. not render the name of a thread
    // for a user who may lack the privileges to see it in the first place.
    \Text::setViewer($Viewer);
}

$Debug->set_flag('load page');
if (DEBUG_MODE || ($Viewer && $Viewer->permitted('site_debug'))) {
    $Twig->addExtension(new Twig\Extension\DebugExtension());
}

// for sections/tools/development/process_info.php
$Cache->cache_value('php_' . getmypid(), [
    'start'    => Time::sqlTime(),
    'document' => $Document,
    'query'    => $_SERVER['QUERY_STRING'],
    'get'      => $_GET,
    'post'     => array_diff_key(
        $_POST,
        array_fill_keys(['password', 'new_pass_1', 'new_pass_2', 'verifypassword', 'confirm_password', 'ChangePassword', 'Password'], true)
    )
], 600);

register_shutdown_function(
    function () {
        if (preg_match(DEBUG_URI, $_SERVER['REQUEST_URI'])) {
            require(DEBUG_TRACE);
        }
        $error = error_get_last();
        if ($error['type'] ?? 0 == E_ERROR) {
            global $Debug;
            $Debug->saveCase(str_replace(SERVER_ROOT . '/', '', $error['message']));
        }
    }
);

// 5. Display the page

header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

$file = realpath(__DIR__ . "/sections/{$Document}/index.php");
if (!$file || !preg_match('/^[a-z][a-z0-9_]+$/', $Document)) {
    error($Viewer ? 403 : 404);
}

try {
    require_once($file);
} catch (Gazelle\DB\MysqlException $e) {
    if (DEBUG_MODE || (isset($Viewer) && $Viewer->permitted('site_debug'))) {
        echo $Twig->render('error-db.twig', [
            'message' => $e->getMessage(),
            'trace'   => str_replace(SERVER_ROOT . '/', '', $e->getTraceAsString()),
        ]);
    } else {
        $Debug->saveError($e);
        error("That is not supposed to happen, please send a Staff Message to \"Staff\" for investigation.");
    }
} catch (\Exception $e) {
    $Debug->saveError($e);
}

// 6. Finish up

$Debug->set_flag('and send to user');
if (!is_null($Viewer)) {
    $Debug->profile($Viewer, $Document);
}
