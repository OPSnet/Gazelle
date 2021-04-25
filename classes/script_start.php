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

$now = microtime(true); //To track how long a page takes to create

require_once(__DIR__ . '/config.php'); //The config contains all site wide configuration information
require_once(__DIR__ . '/util.php');
require_once(__DIR__ . '/../vendor/autoload.php');

use Gazelle\Util\{Crypto, Irc, Text};

//Deal with dumbasses
if (isset($_REQUEST['info_hash']) && isset($_REQUEST['peer_id'])) {
    die('d14:failure reason40:Invalid .torrent, try downloading again.e');
}

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

ob_start(); //Start a buffer, mainly in case there is a mysql error

$Debug = new Gazelle\Debug;
$Debug->setStartTime($now)
    ->handle_errors()
    ->set_flag('init');

$DB = new DB_MYSQL;
G::$DB = $DB;

$Cache = new CACHE;
G::$Cache = $Cache;

$Twig = new Twig\Environment(
    new Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'), [
        'debug' => DEBUG_MODE,
        'cache' => __DIR__ . '/../cache/twig'
]);

$Twig->addFilter(new Twig\TwigFilter(
    'article',
    function ($word) {
        return preg_match('/^[aeiou]/i', $word) ? 'an' : 'a';
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'b64',
    function (string $binary) {
        return base64_encode($binary);
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'bb_format',
    function ($text) {
        return new Twig\Markup(\Text::full_format($text), 'UTF-8');
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'checked',
    function ($isChecked) {
        return $isChecked ? ' checked="checked"' : '';
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'image',
    function ($i) {
        return new Twig\Markup(\ImageTools::process($i, true), 'UTF-8');
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'ipaddr',
    function ($ipaddr) {
        return new Twig\Markup(\Tools::display_ip($ipaddr), 'UTF-8');
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'octet_size',
    function ($size, array $option = []) {
        return \Format::get_size($size, empty($option) ? 2 : $option[0]);
    },
    ['is_variadic' => true]
));

$Twig->addFilter(new Twig\TwigFilter(
    'plural',
    function ($number) {
        return plural($number);
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'selected',
    function ($isSelected) {
        return $isSelected ? ' selected="selected"' : '';
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'shorten',
    function (string $text, int $length) {
        return shortenString($text, $length);
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'time_diff',
    function ($time) {
        return new Twig\Markup(time_diff($time), 'UTF-8');
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'ucfirst',
    function ($text) {
        return ucfirst($text);
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'ucfirstall',
    function ($text) {
        return implode(' ', array_map(function ($w) {return ucfirst($w);}, explode(' ', $text)));
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'user_url',
    function ($userId) {
        return new Twig\Markup(Users::format_username($userId, false, false, false), 'UTF-8');
    }
));

$Twig->addFilter(new Twig\TwigFilter(
    'user_full',
    function ($userId) {
        return new Twig\Markup(\Users::format_username($userId, true, true, true, true), 'UTF-8');
    }
));

$Twig->addFunction(new Twig\TwigFunction('donor_icon', function($icon, $userId) {
    return new Twig\Markup(
        \ImageTools::process($icon, false, 'donoricon', $userId),
        'UTF-8'
    );
}));

$Twig->addFunction(new Twig\TwigFunction('privilege', function ($default, $config, $key) {
    return new Twig\Markup(
        ($default
            ? sprintf(
                '<input id="%s" type="checkbox" disabled="disabled"%s />&nbsp;',
                "default_$key", (isset($default[$key]) && $default[$key] ? ' checked="checked"' : '')
            )
            : ''
        )
        . sprintf(
            '<input type="checkbox" name="%s" id="%s" value="1"%s />&nbsp;<label title="%s" for="%s">%s</label><br />',
            "perm_$key", $key, (empty($config[$key]) ? '' : ' checked="checked"'), $key, $key,
            \Permissions::list()[$key] ?? "!unknown($key)!"
        ),
        'UTF-8'
    );
}));

$Twig->addFunction(new Twig\TwigFunction('ratio', function ($up, $down) {
    return new Twig\Markup(
        \Format::get_ratio_html($up, $down),
        'UTF-8'
    );
}));

$Twig->addFunction(new Twig\TwigFunction('resolveCountryIpv4', function ($addr) {
    return new Twig\Markup(
        (function ($ip) {
            static $cache = [];
            if (!isset($cache[$ip])) {
                $Class = strtr($ip, '.', '-');
                $cache[$ip] = '<span class="cc_'.$Class.'">Resolving CC...'
                    . '<script type="text/javascript">'
                        . '$(document).ready(function() {'
                            . '$.get(\'tools.php?action=get_cc&ip='.$ip.'\', function(cc) {'
                                . '$(\'.cc_'.$Class.'\').html(cc);'
                            . '});'
                        . '});'
                    . '</script></span>';
            }
            return $cache[$ip];
        })($addr),
        'UTF-8'
    );
}));

$Twig->addFunction(new Twig\TwigFunction('resolveIpv4', function ($addr) {
    return new Twig\Markup(
        (function ($ip) {
            if (!$ip) {
                $ip = '127.0.0.1';
            }
            static $cache = [];
            if (!isset($cache[$ip])) {
                $class = strtr($ip, '.', '-');
                $cache[$ip] = '<span class="host_' . $class
                    . '">Resolving host' . "\xE2\x80\xA6" . '<script type="text/javascript">$(document).ready(function() {'
                    .  "\$.get('tools.php?action=get_host&ip=$ip', function(host) {\$('.host_$class').html(host)})})</script></span>";
            }
            return $cache[$ip];
        })($addr),
        'UTF-8'
    );
}));

$Twig->addFunction(new Twig\TwigFunction('shorten', function ($text, $length) {
    return new Twig\Markup(
        shortenString($text, $length),
        'UTF-8'
    );
}));

$Twig->addTest(
    new \Twig\TwigTest('numeric', function ($value) {
        return is_numeric($value);
    })
);

//-- Load user information
// User info is broken up into many sections
// Heavy - Things that the site never has to look at if the user isn't logged in (as opposed to things like the class, donor status, etc)
// Light - Things that appear in format_user
// Stats - Uploaded and downloaded - can be updated by a script if you want super speed
// Session data - Information about the specific session
// Enabled - if the user's enabled or not
// Permissions

// Set the document we are loading
$Document = basename(parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH), '.php');
$userMan = new Gazelle\Manager\User;
$LoggedUser = [];
$SessionID = false;
$FullToken = null;
$user = null;

// Only allow using the Authorization header for ajax endpoint
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && $Document === 'ajax') {
    if ((new Gazelle\Manager\IPv4())->isBanned($_SERVER['REMOTE_ADDR'])) {
        header('Content-type: application/json');
        json_die('failure', 'your ip address has been banned');
    }
    $AuthorizationHeader = explode(" ", (string) $_SERVER['HTTP_AUTHORIZATION']);
    // this first case is for compatibility with RED
    if (count($AuthorizationHeader) === 1) {
        $FullToken = $AuthorizationHeader[0];
    }
    elseif (count($AuthorizationHeader) === 2) {
        if ($AuthorizationHeader[0] !== 'token') {
            header('Content-type: application/json');
            json_die('failure', 'invalid authorization type, must be "token"');
        }
        $FullToken = $AuthorizationHeader[1];
    }
    else {
        header('Content-type: application/json');
        json_die('failure', 'invalid authorization type, must be "token"');
    }

    $Revoked = 1;

    $UserId = (int) substr(Crypto::decrypt(Text::base64UrlDecode($FullToken), ENCKEY), 32);
    if (!empty($UserId)) {
        [$LoggedUser['ID'], $Revoked] = $DB->row('SELECT user_id, revoked FROM api_tokens WHERE user_id=? AND token=?', $UserId, $FullToken);
    }
    $user = $userMan->findById((int)$LoggedUser['ID']);
    if (is_null($user) || $Revoked === 1) {
        log_token_attempt($DB);
        header('Content-type: application/json');
        json_die('failure', 'invalid token');
    }
} elseif (isset($_COOKIE['session'])) {
    $LoginCookie = Crypto::decrypt($_COOKIE['session'], ENCKEY);
    if ($LoginCookie !== false) {
        [$SessionID, $LoggedUser['ID']] = Gazelle\Session::decode($LoginCookie);
        $user = $userMan->findById((int)$LoggedUser['ID']);
        if (is_null($user)) {
            setcookie('session', '', [
                'expires'  => time() - 60 * 60 * 24 * 90,
                'path'     => '/',
                'secure'   => !DEBUG_MODE,
                'httponly' => DEBUG_MODE,
                'samesite' => 'Lax',
            ]);
            header('Location: login.php');
            exit;
        }
        $session = new Gazelle\Session($user->id());
        if (!$session->valid($SessionID)) {
            $user->logout($SessionID);
            header('Location: login.php');
            exit;
        }
        $session->refresh($SessionID);
        $LoggedUser['ID'] = $user->id();
    }
}

if ($user) {
    if (!is_null($FullToken) && !$user->hasApiToken($FullToken)) {
        log_token_attempt($DB, $LoggedUser['ID']);
        header('Content-type: application/json');
        json_die('failure', 'invalid token');
    }

    if ($user->isDisabled()) {
        if (is_null($FullToken)) {
            $user->logout($SessionID);
            header('Location: login.php');
            exit;
        } else {
            log_token_attempt($DB, $LoggedUser['ID']);
            header('Content-type: application/json');
            json_die('failure', 'invalid token');
        }
    }

    // TODO: These globals need to die, and just use $LoggedUser
    // TODO: And then instantiate $LoggedUser from Gazelle\Session when needed
    $LightInfo = Users::user_info($LoggedUser['ID']);
    if (empty($LightInfo['Username'])) { // Ghost
        if (!is_null($FullToken)) {
            $user->flush();
            log_token_attempt($DB, $LoggedUser['ID']);
            header('Content-type: application/json');
            json_die('failure', 'invalid token');
        } else {
            $user->logout();
            header('Location: login.php');
            exit;
        }
    }

    $HeavyInfo = Users::user_heavy_info($LoggedUser['ID']);
    $LoggedUser = array_merge($HeavyInfo, $LightInfo, $user->activityStats());
    G::$LoggedUser =& $LoggedUser;

    // No conditions will force a logout from this point, can hit the DB more.
    // Complete the $LoggedUser array
    $LoggedUser['Permissions'] = Permissions::get_permissions_for_user($LoggedUser['ID'], $LoggedUser['CustomPermissions']);
    $LoggedUser['RSS_Auth'] = md5($LoggedUser['ID'] . RSS_HASH . $LoggedUser['torrent_pass']);

    // Notifications
    if (isset($LoggedUser['Permissions']['site_torrents_notify'])) {
        $LoggedUser['Notify'] = $user->notifyFilters();
    }

    // Stylesheet
    $Stylesheets = new Gazelle\Stylesheet;
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
        $IPv4Man = new Gazelle\Manager\IPv4;
        if ($IPv4Man->isBanned($_SERVER['REMOTE_ADDR'])) {
            error('Your IP address has been banned.');
        }
        $user->updateIP($LoggedUser['IP'], $_SERVER['REMOTE_ADDR']);
    }
}
if (DEBUG_MODE || check_perms('site_debug')) {
    $Twig->addExtension(new Twig\Extension\DebugExtension());
}

function enforce_login() {
    if (!G::$LoggedUser) {
        header('Location: login.php');
        exit;
    }
    global $SessionID, $FullToken, $Document;
    if (!$SessionID && ($Document !== 'ajax' || empty($FullToken))) {
        setcookie('redirect', $_SERVER['REQUEST_URI'], [
            'expires'  => time() + 60 * 30,
            'path'     => '/',
            'secure'   => !DEBUG_MODE,
            'httponly' => DEBUG_MODE,
            'samesite' => 'Lax',
        ]);
        (new Gazelle\User(G::$LoggedUser['ID']))->logout();
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
        Irc::sendRaw("PRIVMSG " . STATUS_CHAN . " :" . G::$LoggedUser['Username'] . " just failed authorize on " . $_SERVER['REQUEST_URI'] . (!empty($_SERVER['HTTP_REFERER']) ? " coming from " . $_SERVER['HTTP_REFERER'] : ""));
        error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);
        return false;
    }
    return true;
}

// We cannot error earlier, as we need the user info for headers and stuff
if (!preg_match('/^[a-z0-9]+$/i', $Document)) {
    error(404);
}

$Debug->set_flag('load page');

// load the appropriate /sections/*/index.php
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

G::$Router = new Gazelle\Router($LoggedUser['AuthKey'] ?? '');
if (isset($LoggedUser['LockedAccount']) && !in_array($Document, ['staffpm', 'ajax', 'locked', 'logout', 'login'])) {
    require_once(__DIR__ . '/../sections/locked/index.php');
}
else {
    $file = __DIR__ . '/../sections/' . $Document . '/index.php';
    if (!file_exists($file)) {
        error(404);
    } else {
        try {
            require_once($file);
        }
        catch (\DB_MYSQL_Exception $e) {
            if (DEBUG_MODE || check_perms('site_debug')) {
?>
<h3>Database error</h3>
<code><?= $e->getMessage() ?></code>
<pre><?= str_replace(SERVER_ROOT .'/', '', $e->getTraceAsString()) ?></pre>
<?php
                View::show_footer();
            } else {
                error("That is not supposed to happen, please send a Staff Message to \"Staff\" for investigation.");
            }
        }
    }
}

if (G::$Router->hasRoutes()) {
    $action = $_REQUEST['action'] ?? '';
    try {
        /** @noinspection PhpIncludeInspection */
        require_once(G::$Router->getRoute($action));
    }
    catch (Gazelle\Exception\RouterException $exception) {
        error(404);
    }
    catch (Gazelle\Exception\InvalidAccessException $exception) {
        error(403);
    }
    catch (\DB_MYSQL_Exception $e) {
        error("That was not supposed to happen, please send a Staff Message to \"Staff\" for investigation.");
    }
}

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

$Debug->set_flag('and send to user');

//Attribute profiling
$Debug->profile();
