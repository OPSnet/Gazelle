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
        case 'ips':
            require_once(__DIR__ . '/ip_history.php');
            break;
        case 'tracker_ips':
            require_once(__DIR__ . '/ip_tracker_history.php');
            break;
        case 'passwords':
            require_once(__DIR__ . '/password_history.php');
            break;
        case 'email':
            require_once(__DIR__ . '/email_history.php');
            break;
        case 'email2':
            require_once(__DIR__ . '/email_history2.php');
            break;
        case 'copypaste':
            require_once(__DIR__ . '/copypaste.php');
            break;
        case 'passkeys':
            require_once(__DIR__ . '/passkey_history.php');
            break;
        case 'posts':
            require_once(__DIR__ . '/post_history.php');
            break;
        case 'topics':
            require_once(__DIR__ . '/topic_history.php');
            break;
        case 'subscriptions':
            require_once(__DIR__ . '/subscriptions.php');
            break;
        case 'thread_subscribe':
            require_once(__DIR__ . '/thread_subscribe.php');
            break;
        case 'comments_subscribe':
            require_once(__DIR__ . '/comments_subscribe.php');
            break;
        case 'catchup':
            require_once(__DIR__ . '/catchup.php');
            break;
        case 'collage_subscribe':
            require_once(__DIR__ . '/collage_subscribe.php');
            break;
        case 'subscribed_collages':
            require_once(__DIR__ . '/subscribed_collages.php');
            break;
        case 'catchup_collages':
            require_once(__DIR__ . '/catchup_collages.php');
            break;
        case 'token_history':
            require_once(__DIR__ . '/token_history.php');
            break;
        case 'quote_notifications':
            require_once(__DIR__ . '/quote_notifications.php');
            break;
        default:
            header('Location: index.php');
    }
}
