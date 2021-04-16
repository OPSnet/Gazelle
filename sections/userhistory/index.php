<?php
/*****************************************************************
User history switch center

This page acts as a switch that includes the real user history pages (to keep
the root less cluttered).

enforce_login() is run here - the entire user history pages are off limits for
non members.
*****************************************************************/

//Include all the basic stuff...
enforce_login();

if ($_GET['action']) {
    switch ($_GET['action']) {
        case 'passkeys':
            require_once('announce_key_history.php');
            break;
        case 'ips':
            require_once('ip_history.php');
            break;
        case 'tracker_ips':
            require_once('ip_tracker_history.php');
            break;
        case 'passwords':
            require_once('password_history.php');
            break;
        case 'email':
            require_once('email_history.php');
            break;
        case 'posts':
            require_once('post_history.php');
            break;
        case 'topics':
            require_once('topic_history.php');
            break;
        case 'subscriptions':
            require_once('subscriptions.php');
            break;
        case 'thread_subscribe':
            require_once('thread_subscribe.php');
            break;
        case 'comments_subscribe':
            require_once('comments_subscribe.php');
            break;
        case 'catchup':
            require_once('catchup.php');
            break;
        case 'collage_subscribe':
            require_once('collage_subscribe.php');
            break;
        case 'subscribed_collages':
            require_once('subscribed_collages.php');
            break;
        case 'catchup_collages':
            require_once('catchup_collages.php');
            break;
        case 'token_history':
            require_once('token_history.php');
            break;
        case 'quote_notifications':
            require_once('quote_notifications.php');
            break;
        default:
            header('Location: index.php');
    }
}
