<?php

if (PHP_VERSION_ID < 70400) {
    die("Gazelle (Orpheus fork) requires PHP 7.4 or later to function properly");
}
if (!extension_loaded('memcached')) {
    die('memcached Extension not loaded.');
}
date_default_timezone_set('UTC');

$PathInfo = pathinfo($_SERVER['SCRIPT_NAME']);
$Document = $PathInfo['filename'];

if ($PathInfo['dirname'] !== '/') {
    exit;
} elseif (in_array($Document, ['announce', 'scrape'])) {
    die("d14:failure reason40:Invalid .torrent, try downloading again.e");
}

$Valid = false;
switch ($Document) {
    case 'peerupdate':
    /** @noinspection PhpMissingBreakStatementInspection */
    case 'schedule':
        define('MEMORY_EXCEPTION', true);
        define('TIME_EXCEPTION', true);
    case 'artist':
    case 'better':
    case 'bookmarks':
    case 'collages':
    case 'comments':
    case 'forums':
    case 'friends':
    case 'torrents':
    case 'upload':
    case 'user':
    case 'userhistory':
    /** @noinspection PhpMissingBreakStatementInspection */
    case 'wiki':
        define('ERROR_EXCEPTION', true);
    case 'ajax':
    case 'apply':
    case 'blog':
    case 'bonus':
    case 'captcha':
    case 'chat':
    case 'contest':
    case 'donate':
    case 'enable':
    case 'error':
    case 'inbox':
    case 'index':
    case 'irc':
    case 'locked':
    case 'log':
    case 'logchecker':
    case 'login':
    case 'logout':
    case 'random':
    case 'recovery':
    case 'referral':
    case 'register':
    case 'reports':
    case 'reportsv2':
    case 'requests':
    case 'rules':
    case 'signup':
    case 'staff':
    case 'staffblog':
    case 'staffpm':
    case 'stats':
    case 'tools':
    case 'top10':
    case 'view':
        $Valid = true;
        break;
}

if (!$Valid) {
    $_SERVER['SCRIPT_NAME'] = 'error.php';
    $_SERVER['SCRIPT_FILENAME'] = 'error.php';
    $Error = 404;
}
require_once('classes/script_start.php');
