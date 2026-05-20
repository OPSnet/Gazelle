<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

namespace Gazelle;

use Gazelle\Util\Crypto;
use Gazelle\Util\Time;

// 1. Initialization

require __DIR__ . '/lib/bootstrap.php';
global $Cache, $Debug, $Twig;
$Debug->mark('bootstrap complete');

register_shutdown_function(
    function () {
        if (preg_match(DEBUG_URI, $_SERVER['REQUEST_URI'])) {
            require DEBUG_TRACE; /** @phpstan-ignore-line */
        }
        $error = error_get_last();
        global $Debug;
        if ($error['type'] == E_ERROR) {
            $Debug->saveCase(str_replace(SERVER_ROOT . '/', '', $error['message']));
        }
        $Debug->storeMemory(memory_get_usage(true));
        $Debug->storeDuration($Debug->duration() * 1000000);
    }
);

// Get the user's actual IP address if they're proxied.
if (
    !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
    && proxyCheck($_SERVER['REMOTE_ADDR'])
    && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

$context = new RequestContext(
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
    || isset($_REQUEST['info_hash'], $_REQUEST['peer_id'])
) {
    die("d14:failure reason40:Invalid .torrent, try downloading again.e");
}

// 2. Do we have a viewer?

Base::setRequestContext($context);
$Viewer    = null;
$cookie    = null;
$banMan    = new Manager\Ban();
$userMan   = new Manager\User();

// Authorization header only makes sense for the ajax endpoint
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $module === 'ajax') {
    if ($banMan->isBanned($context->remoteAddr())) {
        header('Content-type: application/json');
        json_die('failure', 'your ip address has been banned');
    }
    [$success, $result] = $userMan->findByAuthorization($_SERVER['HTTP_AUTHORIZATION']);
    if ($success) {
        $Viewer = $result;
        define('AUTHED_BY_TOKEN', true);
    } else {
        header('Content-type: application/json');
        json_die('failure', $result);
    }
} elseif (isset($_COOKIE['session'])) {
    $cookie = new SessionCookie($_COOKIE['session']);
    $Viewer = $userMan->findByCookie($cookie);
    if (!$Viewer) {
        $cookie->expire();
        header('Location: login.php');
        exit;
    }
} elseif ($module === 'torrents' && ($_REQUEST['action'] ?? '') == 'download' && isset($_REQUEST['torrent_pass'])) {
    $Viewer = $userMan->findByAnnounceKey($_REQUEST['torrent_pass']);
    if (is_null($Viewer) || $Viewer->isDisabled() || $Viewer->isLocked()) {
        http_response_code(403);
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
    // To proxify images (or not), or e.g. not render the name of a thread
    // for a user who may lack the privileges to see it in the first place.
    \Text::setViewer($Viewer);
    Util\Twig::setViewer($Viewer);
    $Viewer->requestContext()->setViewer($Viewer);

    // these endpoints do not exist
    if (in_array($module, OBSOLETE_ENDPOINTS)) {
        $Viewer->session()->logoutEverywhere();
        $cookie?->expire();
        header('Location: login.php');
        exit;
    }
    if ($Viewer->permitted('site_disable_ip_history')) {
        $Viewer->requestContext()->anonymize();
    }
    if ($Viewer->ipaddr() != $Viewer->requestContext()->remoteAddr() && !$Viewer->permitted('site_disable_ip_history')) {
        if ($banMan->isBanned($Viewer->requestContext()->remoteAddr())) {
            Error403::error('Your IP address has been banned.');
        }
        if (!$cookie) {  // api key or passkey (torrent dl)
            new Manager\IPv4()->registerApi($Viewer, $Viewer->requestContext()->remoteAddr());
        }
    }
    $authorized = ['ajax', 'chat', 'locked', 'login', 'logout', 'staffpm'];
    if ($Viewer->isLocked() && !in_array($module, $authorized)) {
        $Viewer->requestContext()->setModule('locked');
        $module = 'locked';
    } elseif ($Viewer->enforceMFA() && !in_array($module, [...$authorized, 'user'])) {
        header("Location: user.php?action=mfa&do=configure&auth=" . $Viewer->auth());
        exit;
    }
}

if (DEBUG_TWIG) {
    $Twig->addExtension(new \Twig\Extension\DebugExtension());
}

// for sections/tools/development/process_info.php
$Cache->cache_value('php_' . getmypid(), [
    'start'    => date('Y-m-d H:i:s'),
    'document' => $module,
    'query'    => $_SERVER['QUERY_STRING'],
    'get'      => $_GET,
    'post'     => array_diff_key(
        $_POST,
        array_fill_keys(['password', 'new_pass_1', 'new_pass_2', 'verifypassword', 'confirm_password', 'ChangePassword', 'Password'], true)
    )
], 600);

// 4. Display the page

header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

$file = realpath(__DIR__ . "/sections/{$module}/index.php");
if ($file === false) {
    Error400::error();
}

try {
    $Debug->mark('call ' . str_replace(SERVER_ROOT . '/', '', $file));
    require $file;
} catch (\Error | \Exception $e) {
    // if there was an ongoing transaction, abort it
    if ($e::class === DB\MysqlException::class) {
        DB::DB()->rollback();
    }
    new Manager\ErrorLog()->pg()->abort();
    $errorLog = $Debug->saveError($e);
    Error500::error(
        $Viewer?->permitted('site_debug')
            ? ($e->getMessage() . " ({$errorLog->link()})")
            : "That is not supposed to happen. Check to see whether someone has created a thread in the the Bugs forum, or create a new thread to explain what you were doing and reference the Error ID {$errorLog->id}."
    );
} finally {
    if (!is_null($Viewer)) {
        $Debug->profile($Viewer, isset($_REQUEST['profile']));
    }
}
