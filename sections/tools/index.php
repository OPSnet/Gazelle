<?php

// TODO: Unify all the code standards and file names (tool_list.php,tool_add.php,tool_alter.php)

if (isset($argv[1])) {
    $_REQUEST['action'] = $argv[1];
}

if (!isset($_REQUEST['action'])) {
    require_once('tools.php');
    die();
}

if (preg_match('/^(?:sandbox|update_geoip)/', $_REQUEST['action']) && !isset($argv[1]) && !$Viewer->permitted('site_debug')) {
    error(403);
}

switch ($_REQUEST['action']) {
    //Managers
    case 'asn_search':
        require_once('managers/asn_search.php');
        break;

    case 'bonus_points':
        require_once('managers/bonus_points.php');
        break;

    case 'categories':
        require_once('managers/categories_list.php');
        break;
    case 'categories_alter':
        require_once('managers/categories_alter.php');
        break;
    case 'change_log':
        require_once('managers/change_log.php');
        break;
    case 'create_user':
        require_once('managers/create_user.php');
        break;
    case 'custom_pm':
        require_once('managers/custom_pm.php');
        break;

    case 'dbkey':
        require_once('managers/db_key.php');
        break;
    case 'dnu':
        require_once('managers/dnu_list.php');
        break;
    case 'dnu_alter':
        require_once('managers/dnu_alter.php');
        break;
    case 'dupe_ips':
        require_once('managers/dupe_ip.php');
        break;

    case 'email_blacklist':
        require_once('managers/email_blacklist.php');
        break;
    case 'email_blacklist_alter':
        require_once('managers/email_blacklist_alter.php');
        break;
    case 'email_search':
        require_once('managers/email_search.php');
        break;
    case 'enable_requests':
        require_once('managers/enable_requests.php');
        break;
    case 'ajax_take_enable_request':
        if (FEATURE_EMAIL_REENABLE) {
            require_once('managers/ajax_take_enable_request.php');
        } else {
            // Prevent post requests to the ajax page
            header("Location: tools.php");
        }
        break;

    case 'featured_album':
        require_once('managers/featured_album.php');
        break;
    case 'forum':
        require_once('managers/forum_list.php');
        break;
    case 'forum_alter':
        require_once('managers/forum_alter.php');
        break;
    case 'forum_transitions':
        require_once('managers/forum_transitions_list.php');
        break;
    case 'forum_transitions_alter':
        require_once('managers/forum_transitions_alter.php');
        break;

    case 'global_notification':
        require_once('managers/global_notification.php');
        break;

    case 'invite_source':
        require_once('managers/invite_source.php');
        break;
    case 'invite_source_config':
        require_once('managers/invite_source_config.php');
        break;
    case 'ip_ban':
        require_once('managers/bans.php');
        break;
    case 'ip_search':
        require_once('managers/ip_search.php');
        break;
    case 'irc':
        require_once('managers/irc_list.php');
        break;
    case 'irc_alter':
        require_once('managers/irc_alter.php');
        break;

    case 'login_watch':
        require_once('managers/login_watch.php');
        break;

    case 'manipulate_tree':
        require_once('managers/manipulate_tree.php');
        break;
    case 'mass_pm':
        require_once('managers/mass_pm.php');
        break;
    case 'take_mass_pm':
        require_once('managers/take_mass_pm.php');
        break;

    case 'navigation_alter':
        require_once('managers/navigation_alter.php');
        break;
    case 'navigation':
        require_once('managers/navigation_list.php');
        break;

    case 'news':
    case 'deletenews':
    case 'editnews':
    case 'takeeditnews':
    case 'takenewnews':
        require_once('managers/news.php');
        break;

    case 'ocelot':
        // this is the callback for ocelot
        require_once('managers/ocelot.php');
        break;
    case 'ocelot_info':
        require_once('managers/ocelot_info.php');
        break;


    case 'userclass':
        require_once('managers/userclass_list.php');
        break;
    case 'privilege-edit':
        require_once('managers/userclass_edit.php');
        break;
    case 'privilege-alter':
        require_once('managers/userclass_alter.php');
        break;
    case 'privilege_matrix':
        require_once('managers/privilege_matrix.php');
        break;

    case 'quick_ban':
        require_once('managers/quick_ban.php');
        break;

    case 'rate_limit':
        require_once('managers/rate_limit.php');
        break;
    case 'reaper':
        require_once('managers/reaper.php');
        break;
    case 'referral_accounts':
        require_once('managers/referral_accounts.php');
        break;
    case 'referral_alter':
        require_once('managers/referral_alter.php');
        break;
    case 'referral_users':
        require_once('managers/referral_users.php');
        break;

    case 'ssl_host':
        require_once('managers/ssl_host.php');
        break;
    case 'staff_groups_alter':
        require_once('managers/staff_groups_alter.php');
        break;
    case 'staff_groups':
        require_once('managers/staff_groups_list.php');
        break;
    case 'stylesheets':
        require_once('managers/stylesheets_list.php');
        break;

    case 'tags':
        require_once('managers/tags.php');
        break;
    case 'tags_aliases':
        require_once('managers/tags_aliases.php');
        break;
    case 'tags_official':
        require_once('managers/tags_official.php');
        break;
    case 'tokens':
        require_once('managers/tokens.php');
        break;
    case 'tor_node':
        require_once('managers/tor_node.php');
        break;
    case 'torrent_report_edit':
        require_once('managers/torrent_report_edit.php');
        break;
    case 'torrent_report_view':
        require_once('managers/torrent_report_view.php');
        break;

    case 'whitelist':
        require_once('managers/whitelist_list.php');
        break;
    case 'whitelist_alter':
        require_once('managers/whitelist_alter.php');
        break;

    // Finances
    case 'donation_log':
        require_once('finances/donation_log.php');
        break;
    case 'donor_rewards':
        require_once('finances/donor_rewards.php');
        break;

    case 'payment_alter':
        require_once('finances/payment_alter.php');
        break;

    case 'payment_list':
        require_once('finances/payment_list.php');
        break;

    //Data
    case 'registration_log':
        require_once('data/registration_log.php');
        break;

    case 'ratio_watch':
        require_once('data/ratio_watch.php');
        break;

    case 'invite_pool':
        require_once('data/invite_pool.php');
        break;
    case 'site_info':
        require_once('data/site_info.php');
        break;

    case 'torrent_stats':
        require_once('data/torrent_stats.php');
        break;

    case 'user_flow':
        require_once('data/user_flow.php');
        break;

    case 'user_info':
        require_once('data/user_info.php');
        break;

    case 'bonus_stats':
        require_once('data/bonus_stats.php');
        break;

    case 'economic_stats':
        require_once('data/economic_stats.php');
        break;

    case 'special_users':
        require_once('data/special_users.php');
        break;

    case 'platform_usage':
        require_once('data/platform_usage.php');
        break;

    // Development
    case 'analysis':
        require_once('development/analysis.php');
        break;
    case 'analysis_list':
        require_once('development/analysis_list.php');
        break;
    case 'database_specifics':
        require_once('development/database_specifics.php');
        break;
    case 'service_stats':
        require_once('development/service_stats.php');
        break;
    case 'update_geoip':
        require_once('development/update_geoip.php');
        break;
    case 'clear_cache':
        require_once('development/clear_cache.php');
        break;
    case 'site_options':
        require_once('development/site_options.php');
        break;
    case 'process_info':
        require_once('development/process_info.php');
        break;

    case 'periodic':
        $mode = $_REQUEST['mode'] ?? 'view';
        switch ($mode) {
            case 'enqueue':
            case 'view':
                require_once('development/periodic_view.php');
                break;
            case 'detail':
                require_once('development/periodic_detail.php');
                break;
            case 'stats':
                require_once('development/periodic_stats.php');
                break;
            case 'edit':
                require_once('development/periodic_edit.php');
                break;
            case 'alter':
                require_once('development/periodic_alter.php');
                break;
            case 'run':
                require_once('development/periodic_run.php');
                break;
        }
        break;

    //Services
    case 'get_host':
        require_once('services/get_host.php');
        break;
    case 'get_cc':
        require_once('services/get_cc.php');
        break;

    case 'bbcode_sandbox':
        if (!$Viewer->permitted('users_mod')) {
            error(403);
        }
        echo $Twig->render('admin/sandbox/bbcode.twig');
        break;
    case 'db_sandbox':
        require_once('sandboxes/db_sandbox.php');
        break;
    case 'notification_sandbox':
        require_once('sandboxes/notification.php');
        break;
    case 'referral_sandbox':
        require_once('sandboxes/referral_sandbox.php');
        break;

    default:
        require_once('tools.php');
}
