<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

use Gazelle\Util\Crypto;
use Gazelle\Util\Time;

// 1. Initialization

require_once(__DIR__ . '/lib/bootstrap.php');
global $Cache, $Debug, $Twig;

// Get the user's actual IP address if they're proxied.
if (
    !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
    && proxyCheck($_SERVER['REMOTE_ADDR'])
    && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

$context = new Gazelle\BaseRequestContext(
    $_SERVER['SCRIPT_NAME'],
    $_SERVER['REMOTE_ADDR'],
    $_SERVER['HTTP_USER_AGENT'] ?? '[no-useragent]',
);
if (!$context->isValid()) {
    exit;
}
$module = $context->module();
if (
    in_array($module, ['announce', 'scrape'])
    || (
        isset($_REQUEST['info_hash'])
        && isset($_REQUEST['peer_id'])
    )
) {
    die("d14:failure reason40:Invalid .torrent, try downloading again.e");
}

// 2. Do we have a viewer?

$SessionID = false;
$Viewer    = null;
$ipv4Man   = new Gazelle\Manager\IPv4();
$userMan   = new Gazelle\Manager\User();
Gazelle\Util\Twig::setUserMan($userMan);

$forceLogout = function (): never {
    setcookie('session', '', [
        'expires'  => time() - 86_400 * 90,
        'path'     => '/',
        'secure'   => !DEBUG_MODE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: login.php');
    exit;
};

// Authorization header only makes sense for the ajax endpoint
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $module === 'ajax') {
    if ($ipv4Man->isBanned($context->remoteAddr())) {
        header('Content-type: application/json');
        json_die('failure', 'your ip address has been banned');
    }
    [$success, $result] = $userMan->findByAuthorization($ipv4Man, $_SERVER['HTTP_AUTHORIZATION']);
    if ($success) {
        $Viewer = $result;
        define('AUTHED_BY_TOKEN', true);
    } else {
        header('Content-type: application/json');
        json_die('failure', $result);
    }
} elseif (isset($_COOKIE['session'])) {
    $cookieData = Crypto::decrypt($_COOKIE['session'], ENCKEY);
    if ($cookieData === false) {
        $forceLogout();
    }
    [$SessionID, $userId] = explode('|~|', $cookieData);
    $Viewer = $userMan->findById((int)$userId);
    if (is_null($Viewer)) {
        $forceLogout();
    }
    if ($Viewer->isDisabled() && !in_array($module, ['index', 'login'])) {
        $Viewer->logoutEverywhere();
        $forceLogout();
    }
    $session = new Gazelle\User\Session($Viewer);
    if (!$session->valid($SessionID)) {
        $Viewer->logout($SessionID);
        $forceLogout();
    }
    $session->refresh($SessionID, $context->remoteAddr(), $context->ua());
    unset($browser, $session, $userId, $cookieData);
} elseif ($module === 'torrents' && ($_REQUEST['action'] ?? '') == 'download' && isset($_REQUEST['torrent_pass'])) {
    $Viewer = $userMan->findByAnnounceKey($_REQUEST['torrent_pass']);
    if (is_null($Viewer) || $Viewer->isDisabled() || $Viewer->isLocked()) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
} elseif (!in_array($module, ['chat', 'enable', 'index', 'login', 'recovery', 'register'])) {
    if (
        // Ocelot is allowed
        !($module === 'tools' && ($_GET['action'] ?? '') === 'ocelot' && ($_GET['key'] ?? '') === TRACKER_SECRET)
    ) {
        // but for everything else, we need a $Viewer
        header('Location: login.php');
        exit;
    }
}

// 3. We have a viewer (or this is a login or registration attempt)

if ($Viewer) {
    // these endpoints do not exist
    if (in_array($module, OBSOLETE_ENDPOINTS)) {
        $Viewer->logoutEverywhere();
        $forceLogout();
    }
    if ($Viewer->hasAttr('admin-error-reporting')) {
        error_reporting(E_ALL);
    }
    if ($Viewer->permitted('site_disable_ip_history')) {
        $context->anonymize();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }
    if ($Viewer->ipaddr() != $context->remoteAddr() && !$Viewer->permitted('site_disable_ip_history')) {
        if ($ipv4Man->isBanned($context->remoteAddr())) {
            error('Your IP address has been banned.');
        }
        $ipv4Man->register($Viewer, $context->remoteAddr());
    }
    if ($Viewer->isLocked() && !in_array($module, ['chat', 'staffpm', 'ajax', 'locked', 'logout', 'login'])) {
        $context->setModule('locked');
    }

    // To proxify images (or not), or e.g. not render the name of a thread
    // for a user who may lack the privileges to see it in the first place.
    \Text::setViewer($Viewer);
}
unset($forceLogout);

$Debug->mark('load page');
if (DEBUG_MODE || ($Viewer && $Viewer->permitted('site_debug'))) {
    $Twig->addExtension(new Twig\Extension\DebugExtension());
}
Gazelle\Base::setRequestContext($context);

// for sections/tools/development/process_info.php
$Cache->cache_value('php_' . getmypid(), [
    'start'    => Time::sqlTime(),
    'document' => $module,
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

// 4. Display the page

header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

$file = realpath(__DIR__ . "/sections/{$module}/index.php");
if (!$file || !preg_match('/^[a-z][a-z0-9_]+$/', $module)) {
    error($Viewer ? 403 : 404);
}

try {
    require_once($file);
} catch (Gazelle\DB\MysqlException $e) {
    Gazelle\DB::DB()->rollback();  // if there was an ongoing transaction, abort it
    if (DEBUG_MODE || (isset($Viewer) && $Viewer->permitted('site_debug'))) {
        echo $Twig->render('error-db.twig', [
            'message' => $e->getMessage(),
            'trace'   => str_replace(SERVER_ROOT . '/', '', $e->getTraceAsString()),
        ]);
    } else {
        $id = $Debug->saveError($e);
        error("That is not supposed to happen, please create a thread in the Bugs forum explaining what you were doing and referencing Error ID $id");
    }
} catch (\Exception $e) {
    $Debug->saveError($e);
}

// 5. Finish up

$Debug->mark('send to user');
if (!is_null($Viewer)) {
    $Debug->profile($Viewer, $module);
}
