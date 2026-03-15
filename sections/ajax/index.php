<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

declare(strict_types=1);

namespace Gazelle;

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
    'push_test'       => [2, 5],
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

if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $input = file_get_contents('php://input');
    if ($input === false) {
        json_error("json decode failure");
    }
    $_POST = json_decode($input, true);
}

header('Content-Type: application/json');

// Enforce rate limiting everywhere except info.php
if (!$Viewer->permitted('site_unlimit_ajax') && isset($LimitedPages[$Action])) {
    [$rate, $interval] = $LimitedPages[$Action];
    if (($UserRequests = $Cache->get_value('ajax_requests_' . $UserID)) === false) {
        $UserRequests = 0;
        $Cache->cache_value('ajax_requests_' . $UserID, '0', $interval);
    } else {
        $Cache->increment_value('ajax_requests_' . $UserID);
        if ($UserRequests > $rate) {
            Util\Irc::sendMessage(IRC_CHAN_STATUS, "ajax rate limit hit by {$Viewer->username()} on $Action");
            json_error("Rate limit exceeded");
        }
    }
}

if ($Viewer->isLocked() || $Viewer->enforceMFA()) {
    if ($Action !== 'preview') {
        json_error("Insufficient privileges");
    }
    require __DIR__ . '/preview.php';
    exit;
}

if (AJAX && !defined('AUTHED_BY_TOKEN') && in_array($Action, $RequireTokenPages)) {
    json_error("This page requires an api token");
}
switch ($Action) {
    // things (that may be) used on the site
    case 'upload_section':
        // Gets one of the upload forms
        require __DIR__ . '/upload.php';
        break;
    case 'preview':
        require __DIR__ . '/preview.php';
        break;
    case 'add_tag':
        require __DIR__ . '/torrent_tag_add.php';
        break;
    case 'delete_tag':
        require __DIR__ . '/torrent_tag_remove.php';
        break;
    case 'stats':
        require __DIR__ . '/stats.php';
        break;

    // things not yet used on the site
    case 'torrent':
        require __DIR__ . '/torrent.php';
        break;
    case 'torrentgroup':
        require __DIR__ . '/torrentgroup.php';
        break;
    case 'torrentgroupalbumart':        // so the album art script can function without breaking the ratelimit
        require __DIR__ . '/torrentgroupalbumart.php';
        break;
    case 'torrent_remove_cover_art':
        require __DIR__ . '/torrent_remove_cover_art.php';
        break;
    case 'tcomments':
        require __DIR__ . '/tcomments.php';
        break;
    case 'user':
        require __DIR__ . '/user.php';
        break;
    case 'forum':
        require __DIR__ . '/forum/index.php';
        break;
    case 'post_edit':
        require __DIR__ . '/post_edit.php';
        break;
    case 'top10':
        require __DIR__ . '/top10/index.php';
        break;
    case 'browse':
        require __DIR__ . '/browse.php';
        break;
    case 'usersearch':
        require __DIR__ . '/usersearch.php';
        break;
    case 'requests':
        require __DIR__ . '/requests.php';
        break;
    case 'artist':
        require __DIR__ . '/artist.php';
        break;
    case 'add_similar':
        require __DIR__ . '/../artist/add_similar.php';
        break;
    case 'inbox':
        require __DIR__ . '/inbox/index.php';
        break;
    case 'subscriptions':
        require __DIR__ . '/subscriptions.php';
        break;
    case 'index':
        require __DIR__ . '/info.php';
        break;
    case 'bookmarks':
        require __DIR__ . '/bookmarks/index.php';
        break;
    case 'announcements':
        require __DIR__ . '/announcements.php';
        break;
    case 'request':
        require __DIR__ . '/request.php';
        break;
    case 'loadavg':
        require __DIR__ . '/loadavg.php';
        break;
    case 'better':
        require __DIR__ . '/better/index.php';
        break;
    case 'password_validate':
        require __DIR__ . '/password_validate.php';
        break;
    case 'similar_artists':
        require __DIR__ . '/similar_artists.php';
        break;
    case 'userhistory':
        match ($_GET['type'] ?? '') {
            'posts' => require __DIR__ . '/userhistory/post_history.php',
            default => json_error('bad type'),
        };
        break;
    case 'votefavorite':
        require __DIR__ . '/vote_handle.php';
        break;
    case 'wiki':
        require __DIR__ . '/wiki.php';
        break;
    case 'get_friends':
        echo json_encode(new User\Friend($Viewer)->userList());
        break;
    case 'news_ajax':
        require __DIR__ . '/news_ajax.php';
        break;
    case 'user_recents':
        require __DIR__ . '/user_recents.php';
        break;
    case 'collage':
        require __DIR__ . '/collage.php';
        break;
    case 'raw_bbcode':
        require __DIR__ . '/raw_bbcode.php';
        break;
    case 'notifications':
        require __DIR__ . '/notifications.php';
        break;
    case 'get_user_notifications':
        require __DIR__ . '/get_user_notifications.php';
        break;
    case 'clear_user_notification':
        require __DIR__ . '/clear_user_notification.php';
        break;
    case 'push_cycle_topic':
        require __DIR__ . '/push_cycle_topic.php';
        break;
    case 'push_test':
        require __DIR__ . '/push_test.php';
        break;
    case 'loggy':
        require __DIR__ . '/loggy.php';
        break;
    case 'user_stats':
        require __DIR__ . '/stats/users.php';
        break;
    case 'torrent_stats':
        require __DIR__ . '/stats/torrents.php';
        break;
    case 'logchecker':
        require __DIR__ . '/logchecker.php';
        break;
    case 'riplog':
        require __DIR__ . '/riplog.php';
        break;

    case 'upload':
        require __DIR__ . '/../upload/upload_handle.php';
        break;
    case 'download':
        require __DIR__ . '/../torrents/download.php';
        break;
    case 'request_fill':
        json_print('success', require __DIR__ . '/../requests/fill_handle.php');
        break;
    case 'add_log':
        require __DIR__ . '/add_log.php';
        break;
    case 'user_torrents':
        require __DIR__ . '/user_torrents.php';
        break;
    default:
        // If they're screwing around with the query string
        json_error("failure");
}
