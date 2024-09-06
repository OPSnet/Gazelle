<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

define('AJAX', !isset($_POST['auth']));

/* 'x' requests every 'y' seconds: [5,10] = 5 requests every 10 seconds */
$LimitedPages = [
    'add_log'         => [5, 10],
    'add_similar'     => [10, 20],
    'add_tag'         => [10, 20],
    'announcements'   => [2, 60],
    'artist'          => [15, 30],
    'better'          => [5, 10],
    'bookmarks'       => [5, 60],
    'browse'          => [5, 10],
    'collage'         => [5, 60],
    'delete_tag'      => [10, 30],
    'forum'           => [5, 10],
    'inbox'           => [15, 60],
    'news_ajax'       => [2, 60],
    'notifications'   => [2, 60],
    'post_edit'       => [2, 10],
    'raw_bbcode'      => [5, 10],
    'request'         => [4, 60],
    'request_fill'    => [5, 10],
    'requests'        => [5, 60],
    'riplog'          => [5, 10],
    'similar_artists' => [10, 60],
    'subscriptions'   => [5, 10],
    'tcomments'       => [5, 10],
    'top10'           => [2, 60],
    'torrentgroup'    => [15, 60],
    'user'            => [4, 60],
    'user_recents'    => [5, 10],
    'userhistory'     => [5, 10],
    'usersearch'      => [5, 60],
    'votefavorite'    => [5, 10],
    'wiki'            => [5, 60],
];
$RequireTokenPages = [
    'download',
    'upload',
    'add_log',
    'add_tag',
    'request_fill',
];

// RED uses the non '_' endpoint, maintaining compat with them here
$Aliases = [
    'addtag' => 'add_tag',
    'requestfill' => 'request_fill',
];

$Action = $_GET['action'] ?? $_POST['action'] ?? '';
if (isset($Aliases[$Action])) {
    $_GET['action'] = $Action = $Aliases[$Action];
}
if (!$Action || !isset($Viewer)) {
    json_error("failure");
}
$UserID = $Viewer->id();

if (!empty($_SERVER['CONTENT_TYPE']) && str_starts_with($_SERVER['CONTENT_TYPE'], 'application/json')) {
    $input = file_get_contents('php://input');
    if ($input === false) {
        error("json decode failure");
    }
    $_POST = json_decode($input, true);
}

header('Content-Type: application/json; charset=utf-8');
// Enforce rate limiting everywhere except info.php
if (!$Viewer->permitted('site_unlimit_ajax') && isset($LimitedPages[$Action])) {
    [$rate, $interval] = $LimitedPages[$Action];
    if (($UserRequests = $Cache->get_value('ajax_requests_' . $UserID)) === false) {
        $UserRequests = 0;
        $Cache->cache_value('ajax_requests_' . $UserID, '0', $interval);
    } else {
        $Cache->increment_value('ajax_requests_' . $UserID);
        if ($UserRequests > $rate) {
            Gazelle\Util\Irc::sendMessage(IRC_CHAN_STATUS, "ajax rate limit hit by {$Viewer->username()} on $Action");
            json_error("Rate limit exceeded");
        }
    }
}

if (AJAX && !defined('AUTHED_BY_TOKEN') && in_array($Action, $RequireTokenPages)) {
    json_error("This page requires an api token");
}

switch ($Action) {
    // things (that may be) used on the site
    case 'upload_section':
        // Gets one of the upload forms
        require_once('upload.php');
        break;
    case 'preview':
        require_once('preview.php');
        break;
    case 'torrent_info':
        require_once('torrent_info.php');
        break;
    case 'add_tag':
        require_once('torrent_tag_add.php');
        break;
    case 'delete_tag':
        require_once('torrent_tag_remove.php');
        break;
    case 'stats':
        require_once('stats.php');
        break;
    case 'checkprivate':
        include('checkprivate.php');
        break;

    // things not yet used on the site
    case 'torrent':
        require_once('torrent.php');
        break;
    case 'torrentgroup':
        require_once('torrentgroup.php');
        break;
    case 'torrentgroupalbumart':        // so the album art script can function without breaking the ratelimit
        require_once('torrentgroupalbumart.php');
        break;
    case 'torrent_remove_cover_art':
        require_once('torrent_remove_cover_art.php');
        break;
    case 'tcomments':
        require_once('tcomments.php');
        break;
    case 'user':
        require_once('user.php');
        break;
    case 'forum':
        require_once('forum/index.php');
        break;
    case 'post_edit':
        require_once('post_edit.php');
        break;
    case 'top10':
        require_once('top10/index.php');
        break;
    case 'browse':
        require_once('browse.php');
        break;
    case 'usersearch':
        require_once('usersearch.php');
        break;
    case 'requests':
        require_once('requests.php');
        break;
    case 'artist':
        require_once('artist.php');
        break;
    case 'add_similar':
        require_once(__DIR__ . '/../artist/add_similar.php');
        break;
    case 'inbox':
        require_once('inbox/index.php');
        break;
    case 'subscriptions':
        require_once('subscriptions.php');
        break;
    case 'index':
        require_once('info.php');
        break;
    case 'bookmarks':
        require_once('bookmarks/index.php');
        break;
    case 'announcements':
        require_once('announcements.php');
        break;
    case 'request':
        require_once('request.php');
        break;
    case 'loadavg':
        require_once('loadavg.php');
        break;
    case 'better':
        require_once('better/index.php');
        break;
    case 'password_validate':
        require_once('password_validate.php');
        break;
    case 'similar_artists':
        require_once('similar_artists.php');
        break;
    case 'userhistory':
        match ($_GET['type'] ?? '') {
            'posts' => require_once('userhistory/post_history.php'),
            default => json_error('bad type'),
        };
        break;
    case 'votefavorite':
        require_once('vote_handle.php');
        break;
    case 'wiki':
        require_once('wiki.php');
        break;
    case 'get_friends':
        echo json_encode((new Gazelle\User\Friend($Viewer))->userList());
        break;
    case 'news_ajax':
        require_once('news_ajax.php');
        break;
    case 'user_recents':
        require_once('user_recents.php');
        break;
    case 'collage':
        require_once('collage.php');
        break;
    case 'raw_bbcode':
        require_once('raw_bbcode.php');
        break;
    case 'notifications':
        require_once('notifications.php');
        break;
    case 'get_user_notifications':
        require_once('get_user_notifications.php');
        break;
    case 'clear_user_notification':
        require_once('clear_user_notification.php');
        break;
    case 'pushbullet_devices':
        require_once('pushbullet_devices.php');
        break;
    case 'loggy':
        require_once('loggy.php');
        break;
    case 'user_stats':
        require_once('stats/users.php');
        break;
    case 'torrent_stats':
        require_once('stats/torrents.php');
        break;
    case 'logchecker':
        require_once('logchecker.php');
        break;
    case 'riplog':
        require_once('riplog.php');
        break;

    case 'upload':
        require_once(__DIR__ . '/../upload/upload_handle.php');
        break;
    case 'download':
        require_once(__DIR__ . '/../torrents/download.php');
        break;
    case 'request_fill':
        json_print('success', require_once(__DIR__ . '/../requests/take_fill.php'));
        break;
    case 'add_log':
        require_once(__DIR__ . '/add_log.php');
        break;
    default:
        // If they're screwing around with the query string
        json_error("failure");
}
