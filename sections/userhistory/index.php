<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

switch ($_GET['action'] ?? '') {
    case 'passkeys':
        require __DIR__ . '/announce_key_history.php';
        break;
    case 'ips':
        require __DIR__ . '/ip_history.php';
        break;
    case 'tracker_ips':
        require __DIR__ . '/ip_tracker_history.php';
        break;
    case 'passwords':
        require __DIR__ . '/password_history.php';
        break;
    case 'email':
        require __DIR__ . '/email_history.php';
        break;
    case 'posts':
        require __DIR__ . '/post_history.php';
        break;
    case 'topics':
        require __DIR__ . '/topic_history.php';
        break;
    case 'subscriptions':
        require __DIR__ . '/subscriptions.php';
        break;
    case 'thread_subscribe':
        require __DIR__ . '/thread_subscribe.php';
        break;
    case 'comments_subscribe':
        require __DIR__ . '/comments_subscribe.php';
        break;
    case 'catchup':
        authorize();
        new User\Subscription($Viewer)->catchupSubscriptions();
        header('Location: userhistory.php?action=subscriptions');
        break;
    case 'collage_subscribe':
        require __DIR__ . '/collage_subscribe.php';
        break;
    case 'subscribed_collages':
        require __DIR__ . '/subscribed_collages.php';
        break;
    case 'catchup_collages':
        require __DIR__ . '/catchup_collages.php';
        break;
    case 'token_history':
        require __DIR__ . '/token_history.php';
        break;
    case 'quote_notifications':
        require __DIR__ . '/quote_notifications.php';
        break;
    case 'reports':
        require __DIR__ . '/report_history.php';
        break;
    default:
        header('Location: index.php');
}
