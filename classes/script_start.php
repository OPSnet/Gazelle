<?php

/*-- Script Start Class --------------------------------*/
/*------------------------------------------------------*/
/* This isnt really a class but a way to tie other      */
/* classes and functions used all over the site to the  */
/* page currently being displayed.                      */
/*------------------------------------------------------*/
/* The code that includes the main php files and        */
/* generates the page are at the bottom.                */
/*------------------------------------------------------*/
/********************************************************/

require_once(__DIR__ . '/config.php'); //The config contains all site wide configuration information
require_once(__DIR__ . '/classloader.php');

use Gazelle\Util\Crypto;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

//Deal with dumbasses
if (isset($_REQUEST['info_hash']) && isset($_REQUEST['peer_id'])) {
    die('d14:failure reason40:Invalid .torrent, try downloading again.e');
}

require_once(__DIR__ . '/proxies.class.php');

// Get the user's actual IP address if they're proxied.
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        && proxyCheck($_SERVER['REMOTE_ADDR'])
        && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'],
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
else if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])
        && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

$ScriptStartTime = microtime(true); //To track how long a page takes to create
if (!defined('PHP_WINDOWS_VERSION_MAJOR')) {
    $RUsage = getrusage();
    $CPUTimeStart = $RUsage['ru_utime.tv_sec'] * 1000000 + $RUsage['ru_utime.tv_usec'];
}

ob_start(); //Start a buffer, mainly in case there is a mysql error

set_include_path(SERVER_ROOT);

require(__DIR__ . '/time.class.php'); //Require the time class
require(__DIR__ . '/paranoia.class.php'); //Require the paranoia check_paranoia function
require(__DIR__ . '/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();
$Debug->set_flag('Debug constructed');

$DB = new DB_MYSQL;
$Debug->set_flag('DB constructed');

$Cache = new CACHE;
$Debug->set_flag('Memcached constructed');

G::$Cache = $Cache;
G::$DB = $DB;
G::$Twig = new Environment(
    new FilesystemLoader(__DIR__ . '/../templates'),
    [
        'debug' => DEBUG_MODE,
        'cache' => __DIR__ . '/../cache/twig'
    ]
);

G::$Twig->addFilter(new \Twig\TwigFilter(
    'number_format',
    function ($text) {
        return number_format($text);
    }
));

G::$Twig->addFilter(new \Twig\TwigFilter(
    'checked',
    function ($isChecked) {
        return $isChecked ? ' checked="checked"' : '';
    }
));

G::$Twig->addFilter(new \Twig\TwigFilter(
    'image',
    function ($i) {
        return ImageTools::process($i, true);
    }
));

G::$Twig->addFilter(new \Twig\TwigFilter(
    'selected',
    function ($isSelected) {
        return $isSelected ? ' selected="selected"' : '';
    }
));

G::$Twig->addFilter(new \Twig\TwigFilter(
    'ucfirstall',
    function ($text) {
        return implode(' ', array_map(function ($w) {return ucfirst($w);}, explode(' ', $text)));
    }
));

G::$Twig->addFunction(new \Twig\TwigFunction('privilege', function ($default, $config, $key) {
    return new \Twig\Markup(
        ($default
            ? sprintf(
                '<input id="%s" type="checkbox" disabled="disabled"%s />&nbsp;',
                "default_$key", (isset($default[$key]) && $default[$key] ? ' checked="checked"' : '')
            )
            : ''
        )
        . sprintf(
            '<input type="checkbox" name="%s" id="%s" value="1"%s />&nbsp;<label for="%s">%s</label><br />',
            "perm_$key", $key, (empty($config[$key]) ? '' : ' checked="checked"'), $key,
            \Permissions::list()[$key] ?? "!unknown($key)!"
        ),
        'UTF-8'
    );
}));

$Debug->set_flag('Twig constructed');

$Debug->set_flag('start user handling');

// Get classes
// TODO: Remove these globals, replace by calls into Users
list($Classes, $ClassLevels) = Users::get_classes();

//-- Load user information
// User info is broken up into many sections
// Heavy - Things that the site never has to look at if the user isn't logged in (as opposed to things like the class, donor status, etc)
// Light - Things that appear in format_user
// Stats - Uploaded and downloaded - can be updated by a script if you want super speed
// Session data - Information about the specific session
// Enabled - if the user's enabled or not
// Permissions

if (isset($_COOKIE['session'])) {
    $LoginCookie = Crypto::decrypt($_COOKIE['session'], ENCKEY);
}
if (isset($LoginCookie)) {
    list($SessionID, $LoggedUser['ID']) = explode('|~|', Crypto::decrypt($LoginCookie, ENCKEY));
    $LoggedUser['ID'] = (int)$LoggedUser['ID'];

    if (!$LoggedUser['ID'] || !$SessionID) {
        logout($LoggedUser['ID'], $SessionID);
    }

    $User = new \Gazelle\User($LoggedUser['ID']);
    $Session = new \Gazelle\Session($LoggedUser['ID']);

    $UserSessions = $Session->sessions();
    if (!array_key_exists($SessionID, $UserSessions)) {
        logout($LoggedUser['ID'], $SessionID);
    }

    if ($User->isDisabled()) {
        logout($LoggedUser['ID'], $SessionID);
    }

    // TODO: These globals need to die, and just use $LoggedUser
    // TODO: And then instantiate $LoggedUser from \Gazelle\Session when needed
    $LightInfo = Users::user_info($LoggedUser['ID']);
    if (empty($LightInfo['Username'])) { // Ghost
        logout($LoggedUser['ID'], $SessionID);
    }

    $UserStats = Users::user_stats($LoggedUser['ID']);
    $HeavyInfo = Users::user_heavy_info($LoggedUser['ID']);

    $LoggedUser = array_merge($HeavyInfo, $LightInfo, $UserStats);
    G::$LoggedUser =& $LoggedUser;

    // No conditions will force a logout from this point, can hit the DB more.
    // Complete the $LoggedUser array
    $LoggedUser['Permissions'] = Permissions::get_permissions_for_user($LoggedUser['ID'], $LoggedUser['CustomPermissions']);
    $donorMan = new Gazelle\Manager\Donation;
    $LoggedUser['Permissions']['MaxCollages'] += $donorMan->personalCollages($LoggedUser['ID']);
    $LoggedUser['RSS_Auth'] = md5($LoggedUser['ID'] . RSS_HASH . $LoggedUser['torrent_pass']);

    // Notifications
    if (isset($LoggedUser['Permissions']['site_torrents_notify'])) {
        $LoggedUser['Notify'] = $User->notifyFilters();
    }

    // Stylesheet
    $Stylesheets = new \Gazelle\Stylesheet;
    $LoggedUser['StyleName'] = $Stylesheets->getName($LoggedUser['StyleID']);

    // We've never had to disable the wiki privs of anyone.
    if ($LoggedUser['DisableWiki']) {
        unset($LoggedUser['Permissions']['site_edit_wiki']);
    }

    // $LoggedUser['RatioWatch'] as a bool to disable things for users on Ratio Watch
    $LoggedUser['RatioWatch'] = (
        time() < strtotime($LoggedUser['RatioWatchEnds'])
        && ($LoggedUser['BytesDownloaded'] * $LoggedUser['RequiredRatio']) > $LoggedUser['BytesUploaded']
    );

    // Change necessary triggers in external components
    $Cache->CanClear = check_perms('admin_clear_cache');

    // Because we <3 our staff
    if (check_perms('site_disable_ip_history')) {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    // IP changed
    if ($LoggedUser['IP'] != $_SERVER['REMOTE_ADDR'] && !check_perms('site_disable_ip_history')) {
        $IPv4Man = new \Gazelle\Manager\IPv4;
        if ($IPv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
            error('Your IP address has been banned.');
        }
        $User->updateIP($LoggedUser['IP'], $_SERVER['REMOTE_ADDR']);
    }

    // Update LastUpdate every 10 minutes
    if (strtotime($UserSessions[$SessionID]['LastUpdate']) + 600 < time()) {
        $userAgent = parse_user_agent($Debug);
        $Session->update([
            'ip-address'      => $_SERVER['REMOTE_ADDR'],
            'browser'         => $userAgent['Browser'],
            'browser-version' => $userAgent['BrowserVersion'],
            'os'              => $userAgent['OperatingSystem'],
            'os-version'      => $userAgent['OperatingSystemVersion'],
            'session-id'      => $SessionID
        ]);
    }
}

$Debug->set_flag('end user handling');
$Debug->set_flag('start function definitions');

function parse_user_agent($Debug) {
    $Debug->set_flag('start parsing user agent');
    if (preg_match("/^Lidarr\/([0-9\.]+) \((.+)\)$/", $_SERVER['HTTP_USER_AGENT'], $Matches) === 1) {
        $OS = explode(" ", $Matches[2]);
        $browserUserAgent = [
            'Browser' => 'Lidarr',
            'BrowserVersion' => substr($Matches[1], 0, strrpos($Matches[1], '.')),
            'OperatingSystem' => $OS[0] === 'macos' ? 'macOS' : ucfirst($OS[0]),
            'OperatingSystemVersion' => $OS[1] ?? null
        ];
    }
    elseif (preg_match("/^VarroaMusica\/([0-9]+(?:dev)?)$/", $_SERVER['HTTP_USER_AGENT'], $Matches) === 1) {
        $browserUserAgent = [
            'Browser' => 'VarroaMusica',
            'BrowserVersion' => str_replace('dev', '', $Matches[1]),
            'OperatingSystem' => null,
            'OperatingSystemVersion' => null
        ];
    }
    elseif (in_array($_SERVER['HTTP_USER_AGENT'], ['Headphones/None', 'whatapi [isaaczafuta]'])) {
        $browserUserAgent = [
            'Browser' => $_SERVER['HTTP_USER_AGENT'],
            'BrowserVersion' => null,
            'OperatingSystem' => null,
            'OperatingSystemVersion' => null
        ];
    }
    else {
        $Result = new WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);
        $Browser = $Result->browser;
        if (empty($Browser->getName()) && !empty($Browser->using)) {
            $Browser = $Browser->using;
        }
        $browserUserAgent = [
            'Browser' => $Browser->getName(),
            'BrowserVersion' => explode('.', $Browser->getVersion())[0],
            'OperatingSystem' => $Result->os->getName(),
            'OperatingSystemVersion' => $Result->os->getVersion()
        ];
    }
    foreach (['Browser', 'BrowserVersion', 'OperatingSystem', 'OperatingSystemVersion'] as $Key) {
        if ($browserUserAgent[$Key] === "") {
            $browserUserAgent[$Key] = null;
        }
    }

    $Debug->set_flag('end parsing user agent');

    return $browserUserAgent;
}

/**
 * Log out the current session
 */
function logout($userId, $sessionId = false) {
    $epoch = time() - 60 * 60 * 24 * 365;
    setcookie('session', '',    $epoch, '/', '', false);
    setcookie('keeplogged', '', $epoch, '/', '', false);
    setcookie('session', '',    $epoch, '/', '', false);
    if ($sessionId) {
        $session = new \Gazelle\Session($userId);
        $session->drop($sessionId);
    }

    G::$Cache->delete_value('user_info_' . $userId);
    G::$Cache->delete_value('user_stats_' . $userId);
    G::$Cache->delete_value('user_info_heavy_' . $userId);

    header('Location: login.php');
    die();
}

/**
 * Logout all sessions
 */
function logout_all_sessions($userId) {
    $session = new \Gazelle\Session($userId);
    $session->dropAll();
    logout($userId);
}

function enforce_login() {
    if (!G::$LoggedUser) {
        header('Location: login.php');
        die();
    }
    global $SessionID;
    if (!$SessionID) {
        setcookie('redirect', $_SERVER['REQUEST_URI'], time() + 60 * 30, '/', '', false);
        logout(G::$LoggedUser['ID']);
    }
}

/**
 * Make sure $_GET['auth'] is the same as the user's authorization key
 * Should be used for any user action that relies solely on GET.
 *
 * @param bool Are we using ajax?
 * @return bool authorisation status. Prints an error message to LAB_CHAN on IRC on failure.
 */
function authorize($Ajax = false) {
    if (empty($_REQUEST['auth']) || $_REQUEST['auth'] != G::$LoggedUser['AuthKey']) {
        send_irc("PRIVMSG " . STATUS_CHAN . " :" . G::$LoggedUser['Username'] . " just failed authorize on " . $_SERVER['REQUEST_URI'] . (!empty($_SERVER['HTTP_REFERER']) ? " coming from " . $_SERVER['HTTP_REFERER'] : ""));
        error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);
        return false;
    }
    return true;
}

function authorizeIfPost($Ajax = false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['auth']) || $_POST['auth'] != G::$LoggedUser['AuthKey']) {
            send_irc("PRIVMSG " . STATUS_CHAN . " :" . G::$LoggedUser['Username'] . " just failed authorize on " . $_SERVER['REQUEST_URI'] . (!empty($_SERVER['HTTP_REFERER']) ? " coming from " . $_SERVER['HTTP_REFERER'] : ""));
            error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);
            return false;
        }
    }
    return true;
}

$Debug->set_flag('ending function definitions');

// load the appropriate /sections/*/index.php
$Document = basename(parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH), '.php');
if (!preg_match('/^[a-z0-9]+$/i', $Document)) {
    error(404);
}

$Cache->cache_value('php_' . getmypid(),
    [
        'start' => sqltime(),
        'document' => $Document,
        'query' => $_SERVER['QUERY_STRING'],
        'get' => $_GET,
        'post' => array_diff_key(
            $_POST,
            array_fill_keys(['password', 'cur_pass', 'new_pass_1', 'new_pass_2', 'verifypassword', 'confirm_password', 'ChangePassword', 'Password'], true)
        )
    ], 600
);

G::$Router = new \Gazelle\Router($LoggedUser['AuthKey']);
if (isset($LoggedUser['LockedAccount']) && !in_array($Document, ['staffpm', 'ajax', 'locked', 'logout', 'login'])) {
    require(__DIR__ . '/../sections/locked/index.php');
}
else {
    $file = __DIR__ . '/../sections/' . $Document . '/index.php';
    if (!file_exists($file)) {
        error(404);
    }
    else {
        require($file);
    }
}

if (G::$Router->hasRoutes()) {
    $action = $_REQUEST['action'] ?? '';
    try {
        /** @noinspection PhpIncludeInspection */
        require_once(G::$Router->getRoute($action));
    }
    catch (\Gazelle\Exception\RouterException $exception) {
        error(404);
    }
    catch (\Gazelle\Exception\InvalidAccessException $exception) {
        error(403);
    }
}

$Debug->set_flag('completed module execution');

/* Required in the absence of session_start() for providing that pages will change
 * upon hit rather than being browser cached for changing content.
 * Old versions of Internet Explorer choke when downloading binary files over HTTPS with disabled cache.
 * Define the following constant in files that handle file downloads.
 */
if (!defined('SKIP_NO_CACHE_HEADERS')) {
    header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: no-cache');
}

ob_end_flush();

$Debug->set_flag('set headers and send to user');

//Attribute profiling
$Debug->profile();
