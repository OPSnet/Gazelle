<?php

use \Gazelle\Manager\Notification;

/*****************************************************************
    Tools switch center

    This page acts as a switch for the tools pages.

    TODO!
    -Unify all the code standards and file names (tool_list.php,tool_add.php,tool_alter.php)

 *****************************************************************/

if (isset($argv[1])) {
    $_REQUEST['action'] = $argv[1];
} else {
    if (empty($_REQUEST['action']) || ($_REQUEST['action'] != 'public_sandbox' && $_REQUEST['action'] != 'ocelot')) {
        enforce_login();
    }
}

if (!isset($_REQUEST['action'])) {
    require(__DIR__ . '/tools.php');
    die();
}

if (substr($_REQUEST['action'], 0, 7) == 'sandbox' && !isset($argv[1])) {
    if (!check_perms('site_debug')) {
        error(403);
    }
}

if (substr($_REQUEST['action'], 0, 12) == 'update_geoip' && !isset($argv[1])) {
    if (!check_perms('site_debug')) {
        error(403);
    }
}

if (substr($_REQUEST['action'],0,16) == 'rerender_gallery' && !isset($argv[1])) {
    if (!check_perms('site_debug')) {
        error(403);
    }
}

$Val = new Validate;
$Feed = new Feed;

switch ($_REQUEST['action']) {
    //Services
    case 'get_host':
        require(__DIR__ . '/services/get_host.php');
        break;
    case 'get_cc':
        require(__DIR__ . '/services/get_cc.php');
        break;
    //Managers
    case 'categories':
        require(__DIR__ . '/managers/categories_list.php');
        break;

    case 'categories_alter':
        require(__DIR__ . '/managers/categories_alter.php');
        break;

    case 'forum':
        require(__DIR__ . '/managers/forum_list.php');
        break;

    case 'forum_alter':
        require(__DIR__ . '/managers/forum_alter.php');
        break;

    case 'irc':
        require(__DIR__ . '/managers/irc_list.php');
        break;

    case 'dbkey':
        require(__DIR__ . '/managers/db_key.php');
        break;

    case 'navigation_alter':
        require(__DIR__ . '/managers/navigation_alter.php');
        break;

    case 'navigation':
        require(__DIR__ . '/managers/navigation_list.php');
        break;

    case 'forum_transitions':
        require(__DIR__ . '/managers/forum_transitions_list.php');
        break;

    case 'forum_transitions_alter':
        require(__DIR__ . '/managers/forum_transitions_alter.php');
        break;

    case 'irc_alter':
        require(__DIR__ . '/managers/irc_alter.php');
        break;

    case 'whitelist':
        require(__DIR__ . '/managers/whitelist_list.php');
        break;

    case 'whitelist_alter':
        require(__DIR__ . '/managers/whitelist_alter.php');
        break;

    case 'referral_accounts':
        require(__DIR__ . '/managers/referral_accounts.php');
        break;

    case 'referral_alter':
        require(__DIR__ . '/managers/referral_alter.php');
        break;

    case 'referral_users':
        require(__DIR__ . '/managers/referral_users.php');
        break;

    case 'payment_alter':
        require(__DIR__ . '/finances/payment_alter.php');
        break;

    case 'payment_list':
        require(__DIR__ . '/finances/payment_list.php');
        break;

    case 'enable_requests':
        require(__DIR__ . '/managers/enable_requests.php');
        break;
    case 'ajax_take_enable_request':
        if (FEATURE_EMAIL_REENABLE) {
            require(__DIR__ . '/managers/ajax_take_enable_request.php');
        } else {
            // Prevent post requests to the ajax page
            header("Location: tools.php");
            die();
        }
        break;

    case 'login_watch':
        require(__DIR__ . '/managers/login_watch.php');
        break;

    case 'email_blacklist':
        require(__DIR__ . '/managers/email_blacklist.php');
        break;

    case 'email_blacklist_alter':
        require(__DIR__ . '/managers/email_blacklist_alter.php');
        break;

    case 'email_blacklist_search':
        require(__DIR__ . '/managers/email_blacklist_search.php');
        break;

    case 'dnu':
        require(__DIR__ . '/managers/dnu_list.php');
        break;

    case 'dnu_alter':
        require(__DIR__ . '/managers/dnu_alter.php');
        break;

    case 'editnews':
    case 'news':
        require(__DIR__ . '/managers/news.php');
        break;

    case 'takeeditnews':
        if (!check_perms('admin_manage_news')) {
            error(403);
        }
        if (is_number($_POST['newsid'])) {
            $DB->prepared_query("
                UPDATE news
                SET Title = ?,
                    Body = ?
                WHERE ID = ?
            ", $_POST['title'], $_POST['body'], $_POST['newsid']);
            $Cache->delete_value('news');
            $Cache->delete_value('feed_news');
        }
        header('Location: index.php');
        break;

    case 'deletenews':
        if (!check_perms('admin_manage_news')) {
            error(403);
        }
        if (is_number($_GET['id'])) {
            authorize();
            $DB->prepared_query("
                DELETE FROM news
                WHERE ID = ?
            ", $_GET['id']);
            $Cache->delete_value('news');
            $Cache->delete_value('feed_news');

            // Deleting latest news
            $LatestNews = $Cache->get_value('news_latest_id');
            if ($LatestNews !== false && $LatestNews == $_GET['id']) {
                $Cache->delete_value('news_latest_id');
                $Cache->delete_value('news_latest_title');
            }
        }
        header('Location: index.php');
        break;

    case 'takenewnews':
        if (!check_perms('admin_manage_news')) {
            error(403);
        }

        $DB->prepared_query("
            INSERT INTO news (UserID, Title, Body)
            VALUES (?, ?, ?)
        ", $LoggedUser['ID'], $_POST['title'], $_POST['body']);
        $Cache->delete_value('news_latest_id');
        $Cache->delete_value('news_latest_title');
        $Cache->delete_value('news');

        $notification = new Notification(G::$LoggedUser['ID']);
        $notification->push($notification->pushableUsers(), $_POST['title'], $_POST['body'], site_url() . 'index.php', Notification::NEWS);

        header('Location: index.php');
        break;

    case 'bonus_points':
        require(__DIR__ . '/managers/bonus_points.php');
        break;
    case 'tokens':
        require(__DIR__ . '/managers/tokens.php');
        break;
    case 'multiple_freeleech':
        require(__DIR__ . '/managers/multiple_freeleech.php');
        break;
    case 'ocelot':
        require(__DIR__ . '/managers/ocelot.php');
        break;
    case 'tags':
        require(__DIR__ . '/managers/tags.php');
        break;
    case 'tags_aliases':
        require(__DIR__ . '/managers/tags_aliases.php');
        break;
    case 'tags_official':
        require(__DIR__ . '/managers/tags_official.php');
        break;
    case 'label_aliases':
        require(__DIR__ . '/managers/label_aliases.php');
        break;
    case 'change_log':
        require(__DIR__ . '/managers/change_log.php');
        break;
    case 'global_notification':
        require(__DIR__ . '/managers/global_notification.php');
        break;
    case 'take_global_notification':
        require(__DIR__ . '/managers/take_global_notification.php');
        break;
    case 'permissions':
        // this is retarded and doesn't always alter things but it's better than being in __FILE__
        require(__DIR__ . '/managers/permissions_alter.php');
        break;
    case 'privilege_matrix':
        require(__DIR__ . '/managers/privilege_matrix.php');
        break;
    case 'staff_groups_alter':
        require(__DIR__ . '/managers/staff_groups_alter.php');
        break;
    case 'staff_groups':
        require(__DIR__ . '/managers/staff_groups_list.php');
        break;
    case 'ip_ban':
        require(__DIR__ . '/managers/bans.php');
        break;
    case 'quick_ban':
        require(__DIR__ . '/managers/quick_ban.php');
        break;
    case 'calendar':
        require(__DIR__ . '/managers/calendar.php');
        break;
    case 'get_calendar_event':
        require(__DIR__ . '/managers/ajax_get_calendar_event.php');
        break;
    case 'take_calendar_event':
        require(__DIR__ . '/managers/ajax_take_calendar_event.php');
        break;
    case 'stylesheets':
        require(__DIR__ . '/managers/stylesheets_list.php');
        break;
    case 'mass_pm':
        require(__DIR__ . '/managers/mass_pm.php');
        break;
    case 'take_mass_pm':
        require(__DIR__ . '/managers/take_mass_pm.php');
        break;

    case 'rate_limit':
        require(__DIR__ . '/managers/rate_limit.php');
        break;

    case 'donation_log':
        require(__DIR__ . '/finances/btc_log.php');
        break;

    case 'bitcoin_balance':
        require(__DIR__ . '/finances/bitcoin_balance.php');
        break;

    case 'donor_rewards':
        require(__DIR__ . '/finances/donor_rewards.php');
        break;

    //Data
    case 'ocelot_info':
        require(__DIR__ . '/data/ocelot_info.php');
        break;

    case 'registration_log':
        require(__DIR__ . '/data/registration_log.php');
        break;

    case 'upscale_pool':
        require(__DIR__ . '/data/upscale_pool.php');
        break;

    case 'invite_pool':
        require(__DIR__ . '/data/invite_pool.php');
        break;

    case 'torrent_stats':
        require(__DIR__ . '/data/torrent_stats.php');
        break;

    case 'user_flow':
        require(__DIR__ . '/data/user_flow.php');
        break;

    case 'economic_stats':
        require(__DIR__ . '/data/economic_stats.php');
        break;

    case 'database_specifics':
        require(__DIR__ . '/data/database_specifics.php');
        break;

    case 'special_users':
        require(__DIR__ . '/data/special_users.php');
        break;

    case 'platform_usage':
        require(__DIR__ . '/data/platform_usage.php');
        break;
    //END Data

    //Misc
    case 'service_stats':
        require(__DIR__ . '/development/service_stats.php');
        break;

    case 'update_geoip':
        require(__DIR__ . '/development/update_geoip.php');
        break;

    case 'clear_cache':
        require(__DIR__ . '/development/clear_cache.php');
        break;

    case 'site_info':
        require(__DIR__ . '/development/site_info.php');
        break;

    case 'site_options':
        require(__DIR__ . '/development/site_options.php');
        break;

    case 'process_info':
        require(__DIR__ . '/development/process_info.php');
        break;

    case 'rerender_gallery':
        require(__DIR__ . '/development/rerender_gallery.php');
        break;

    case 'periodic':
        $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'view';
        switch ($mode) {
            case 'run_now':
            case 'view':
                require(__DIR__ . '/development/periodic_view.php');
                break;
            case 'detail':
                require(__DIR__ . '/development/periodic_detail.php');
                break;
            case 'stats':
                require(__DIR__ . '/development/periodic_stats.php');
                break;
            case 'edit':
                require(__DIR__ . '/development/periodic_edit.php');
                break;
            case 'alter':
                require(__DIR__ . '/development/periodic_alter.php');
                break;
        }
        break;

    case 'analysis':
        require(__DIR__ . '/misc/analysis.php');
        break;

    case 'dupe_ips':
        require(__DIR__ . '/misc/dupe_ip.php');
        break;

    case 'create_user':
        require(__DIR__ . '/misc/create_user.php');
        break;

    case 'manipulate_tree':
        require(__DIR__ . '/misc/manipulate_tree.php');
        break;

    case 'analysis_list':
        require(__DIR__  . '/misc/analysis_list.php');
        break;

    case 'monthalbum':
        require(__DIR__ . '/misc/album_of_month.php');
        break;

    case 'vanityhouse':
        require(__DIR__ . '/misc/vanity_house.php');
        break;

    case 'public_sandbox':
        require(__DIR__ . '/sandboxes/public_sandbox.php');
        break;

    case 'mod_sandbox':
        if (check_perms('users_mod')) {
            require(__DIR__ . '/sandboxes/mod_sandbox.php');
        } else {
            error(403);
        }
        break;
    case 'bbcode_sandbox':
        require(__DIR__ . '/sandboxes/bbcode_sandbox.php');
        break;
    case 'artist_importance_sandbox':
        require(__DIR__ . '/sandboxes/artist_importance_sandbox.php');
        break;
    case 'db_sandbox':
        require(__DIR__ . '/sandboxes/db_sandbox.php');
        break;
    case 'referral_sandbox':
        require(__DIR__ . '/sandboxes/referral_sandbox.php');
        break;

    default:
        require(__DIR__ . '/tools.php');
}
