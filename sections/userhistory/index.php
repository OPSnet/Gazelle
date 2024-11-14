<?php
/** @phpstan-var \Gazelle\User $Viewer */

switch ($_GET['action'] ?? '') {
    case 'passkeys':
        include_once 'announce_key_history.php';
        break;
    case 'ips':
        include_once 'ip_history.php';
        break;
    case 'tracker_ips':
        include_once 'ip_tracker_history.php';
        break;
    case 'passwords':
        include_once 'password_history.php';
        break;
    case 'email':
        include_once 'email_history.php';
        break;
    case 'posts':
        include_once 'post_history.php';
        break;
    case 'topics':
        include_once 'topic_history.php';
        break;
    case 'subscriptions':
        include_once 'subscriptions.php';
        break;
    case 'thread_subscribe':
        include_once 'thread_subscribe.php';
        break;
    case 'comments_subscribe':
        include_once 'comments_subscribe.php';
        break;
    case 'catchup':
        authorize();
        (new Gazelle\User\Subscription($Viewer))->catchupSubscriptions();
        header('Location: userhistory.php?action=subscriptions');
        break;
    case 'collage_subscribe':
        include_once 'collage_subscribe.php';
        break;
    case 'subscribed_collages':
        include_once 'subscribed_collages.php';
        break;
    case 'catchup_collages':
        include_once 'catchup_collages.php';
        break;
    case 'token_history':
        include_once 'token_history.php';
        break;
    case 'quote_notifications':
        include_once 'quote_notifications.php';
        break;
    default:
        header('Location: index.php');
}
