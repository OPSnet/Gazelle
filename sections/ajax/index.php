<?php
/*
AJAX Switch Center

This page acts as an AJAX "switch" - it's called by scripts, and it includes the required pages.

The required page is determined by $_GET['action'].

*/

enforce_login();

define('AJAX', true);

/* 'x' requests every 'y' seconds: [5,10] = 5 requests every 10 seconds */
$LimitedPages = [
    'add_similar'     => [5, 10],
    'add_tag'         => [5, 10],
    'addtag'          => [5, 10],
    'announcements'   => [2, 60],
    'artist'          => [5, 10],
    'better'          => [5, 10],
    'bookmarks'       => [5, 60],
    'browse'          => [5, 10],
    'collage'         => [5, 60],
    'forum'           => [5, 10],
    'inbox'           => [2, 60],
    'news_ajax'       => [2, 60],
    'notifications'   => [2, 60],
    'raw_bbcode'      => [5, 10],
    'request'         => [4, 60],
    'request_fill'    => [5, 10],
    'requestfill'     => [5, 10],
    'requests'        => [5, 60],
    'riplog'          => [5, 10],
    'similar_artists' => [4, 60],
    'subscriptions'   => [5, 10],
    'tcomments'       => [5, 10],
    'top10'           => [2, 60],
    'torrentgroup'    => [5, 60],
    'user'            => [4, 60],
    'user_recents'    => [5, 10],
    'userhistory'     => [5, 10],
    'usersearch'      => [5, 10],
    'votefavorite'    => [5, 10],
    'wiki'            => [5, 60],
];
$RequireTokenPages = [
    'download',
    'upload',
];

$UserID = $LoggedUser['ID'];

if (!empty($_SERVER['CONTENT_TYPE']) && substr($_SERVER['CONTENT_TYPE'], 0, 16) === 'application/json') {
    $_POST = json_decode(file_get_contents('php://input'), true);
}

header('Content-Type: application/json; charset=utf-8');
// Enforce rate limiting everywhere except info.php
if (!check_perms('site_unlimit_ajax') && isset($_GET['action']) && isset($LimitedPages[$_GET['action']])) {
    [$rate, $interval] = $LimitedPages[$_GET['action']];
    if (($UserRequests = $Cache->get_value('ajax_requests_'.$UserID)) === false) {
        $UserRequests = 0;
        $Cache->cache_value('ajax_requests_'.$UserID, '0', $interval);
    } else {
        $Cache->increment_value('ajax_requests_'.$UserID);
        if ($UserRequests > $rate) {
            json_die("failure", "Rate limit exceeded");
        }
    }
}

if (!isset($FullToken) && in_array($_GET['action'], $RequireTokenPages)) {
    json_die("failure", "This page requires an api token");
}

switch ($_GET['action']) {
    // things (that may be) used on the site
    case 'upload_section':
        // Gets one of the upload forms
        require('upload.php');
        break;
    case 'preview':
        require('preview.php');
        break;
    case 'torrent_info':
        require('torrent_info.php');
        break;
    case 'stats':
        require('stats.php');
        break;
    case 'checkprivate':
        include('checkprivate.php');
        break;

    // things not yet used on the site
    case 'torrent':
        require('torrent.php');
        break;
    case 'torrentgroup':
        require('torrentgroup.php');
        break;
    case 'torrentgroupalbumart':        // so the album art script can function without breaking the ratelimit
        require('torrentgroupalbumart.php');
        break;
    case 'tcomments':
        require('tcomments.php');
        break;
    case 'user':
        require('user.php');
        break;
    case 'forum':
        require('forum/index.php');
        break;
    case 'top10':
        require('top10/index.php');
        break;
    case 'browse':
        require('browse.php');
        break;
    case 'usersearch':
        require('usersearch.php');
        break;
    case 'requests':
        require('requests.php');
        break;
    case 'artist':
        require('artist.php');
        break;
    case 'add_similar':
        require(__DIR__ . '/../artist/add_similar.php');
        break;
    case 'inbox':
        require('inbox/index.php');
        break;
    case 'subscriptions':
        require('subscriptions.php');
        break;
    case 'index':
        require('info.php');
        break;
    case 'bookmarks':
        require('bookmarks/index.php');
        break;
    case 'announcements':
        require('announcements.php');
        break;
    case 'notifications':
        require('notifications.php');
        break;
    case 'request':
        require('request.php');
        break;
    case 'loadavg':
        require('loadavg.php');
        break;
    case 'better':
        require('better/index.php');
        break;
    case 'password_validate':
        require('password_validate.php');
        break;
    case 'similar_artists':
        require('similar_artists.php');
        break;
    case 'userhistory':
        switch ($_GET['type'] ?? '') {
            case 'posts':
                require('userhistory/post_history.php');
                break;
            default:
                json_die('bad type');
                break;
        }
        break;
    case 'votefavorite':
        require('takevote.php');
        break;
    case 'wiki':
        require('wiki.php');
        break;
    case 'get_friends':
        require('get_friends.php');
        break;
    case 'news_ajax':
        require('news_ajax.php');
        break;
    case 'community_stats':
        require('community_stats.php');
        break;
    case 'user_recents':
        require('user_recents.php');
        break;
    case 'collage':
        require('collage.php');
        break;
    case 'raw_bbcode':
        require('raw_bbcode.php');
        break;
    case 'get_user_notifications':
        require('get_user_notifications.php');
        break;
    case 'clear_user_notification':
        require('clear_user_notification.php');
        break;
    case 'pushbullet_devices':
        require('pushbullet_devices.php');
        break;
    case 'loggy':
        require('loggy.php');
        break;
    case 'user_stats':
        require('stats/users.php');
        break;
    case 'torrent_stats':
        require('stats/torrents.php');
        break;
    case 'logchecker':
        require('logchecker.php');
        break;
    case 'riplog':
        require('riplog.php');
        break;

    case 'upload':
        require(__DIR__ . '/../upload/upload_handle.php');
        break;
    case 'download':
        require(__DIR__ . '/../torrents/download.php');
        break;

    // RED uses the non '_' endpoint, maintaining compat with them here
    case 'request_fill':
    case 'requestfill':
        json_print('success', require(__DIR__ . '/../requests/take_fill.php'));
        break;
    case 'add_tag':
    case 'addtag':
        require(__DIR__ . '/../torrents/add_tag.php');
        break;
    default:
        // If they're screwing around with the query string
        json_die("failure");
}
