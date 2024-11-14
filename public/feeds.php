<?php

// Prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

require_once __DIR__ . '/../lib/bootstrap.php';

$user = (new Gazelle\Manager\User())->findById((int)($_GET['user'] ?? 0));
if (is_null($user)) {
    die((new Gazelle\Feed())->blocked());
}

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
if ($user->permitted('site_disable_ip_history')) {
    $context->anonymize();
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
Gazelle\Base::setRequestContext($context);

if (
    !$user->isEnabled()
    || empty($_GET['feed'])
    || md5($user->id() . RSS_HASH . ($_GET['passkey'] ?? 'NOTPASS')) !== ($_GET['auth'] ?? 'NOTAUTH')
) {
    die((new Gazelle\Feed())->blocked());
}

$feed = new Gazelle\Feed();
switch ($_GET['feed']) {
    case 'torrents_abooks':
    case 'torrents_all':
    case 'torrents_apps':
    case 'torrents_comedy':
    case 'torrents_comics':
    case 'torrents_ebooks':
    case 'torrents_evids':
    case 'torrents_flac':
    case 'torrents_lossless':
    case 'torrents_lossless24':
    case 'torrents_mp3':
    case 'torrents_music':
    case 'torrents_vinyl':
        echo $feed->byFeedName($user, $_GET['feed']);
        break;
    case 'feed_news':
        echo $feed->news(new Gazelle\Manager\News());
        break;
    case 'feed_blog':
        echo $feed->blog(new Gazelle\Manager\Blog(), new Gazelle\Manager\ForumThread());
        break;
    case 'feed_changelog':
        echo $feed->changelog(new Gazelle\Manager\Changelog());
        break;
    default:
        echo match (true) {
            str_starts_with($_GET['feed'], 'torrents_bookmarks_t_') => $feed->bookmark($user, $_GET['feed']),
            str_starts_with($_GET['feed'], 'torrents_notify_') =>      $feed->personal($user, $_GET['feed'], $_GET['name'] ?? null),
            default => $feed->blocked()
        };
}
