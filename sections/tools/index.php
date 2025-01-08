<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

switch ($_REQUEST['action'] ?? '') {
    //Managers
    case 'asn_search':
        include_once 'managers/asn_search.php';
        break;

    case 'bonus_points':
        include_once 'managers/bonus_points.php';
        break;

    case 'categories':
        include_once 'managers/categories_list.php';
        break;
    case 'categories_alter':
        include_once 'managers/categories_alter.php';
        break;
    case 'change_log':
        include_once 'managers/change_log.php';
        break;
    case 'create_user':
        include_once 'managers/create_user.php';
        break;
    case 'custom_pm':
        include_once 'managers/custom_pm.php';
        break;

    case 'dbkey':
        include_once 'managers/db_key.php';
        break;
    case 'dnu':
        include_once 'managers/dnu_list.php';
        break;
    case 'dnu_alter':
        include_once 'managers/dnu_alter.php';
        break;
    case 'dupe_ips':
        include_once 'managers/dupe_ip.php';
        break;

    case 'email_blacklist':
        include_once 'managers/email_blacklist.php';
        break;
    case 'email_blacklist_alter':
        include_once 'managers/email_blacklist_alter.php';
        break;
    case 'email_search':
        include_once 'managers/email_search.php';
        break;
    case 'enable_requests':
        include_once 'managers/enable_requests.php';
        break;
    case 'ajax_take_enable_request':
        if (FEATURE_EMAIL_REENABLE) {
            include_once 'managers/ajax_take_enable_request.php';
        } else {
            // Prevent post requests to the ajax page
            header("Location: tools.php");
        }
        break;

    case 'featured_album':
        include_once 'managers/featured_album.php';
        break;
    case 'forum':
        include_once 'managers/forum_list.php';
        break;
    case 'forum_alter':
        include_once 'managers/forum_alter.php';
        break;
    case 'forum_transitions':
        include_once 'managers/forum_transitions_list.php';
        break;
    case 'forum_transitions_alter':
        include_once 'managers/forum_transitions_alter.php';
        break;

    case 'global_notification':
        include_once 'managers/global_notification.php';
        break;

    case 'invite_source':
        include_once 'managers/invite_source.php';
        break;
    case 'invite_source_config':
        include_once 'managers/invite_source_config.php';
        break;
    case 'ip_ban':
        include_once 'managers/bans.php';
        break;
    case 'ip_search':
        include_once 'managers/ip_search.php';
        break;

    case 'login_watch':
        include_once 'managers/login_watch.php';
        break;

    case 'manipulate_tree':
        include_once 'managers/manipulate_tree.php';
        break;
    case 'mass_pm':
        include_once 'managers/mass_pm.php';
        break;
    case 'take_mass_pm':
        include_once 'managers/take_mass_pm.php';
        break;

    case 'navigation_alter':
        include_once 'managers/navigation_alter.php';
        break;
    case 'navigation':
        include_once 'managers/navigation_list.php';
        break;

    case 'news':
    case 'deletenews':
    case 'editnews':
    case 'takeeditnews':
    case 'takenewnews':
        include_once 'managers/news.php';
        break;

    case 'ocelot':
        // this is the callback for ocelot
        include_once 'managers/ocelot.php';
        break;
    case 'ocelot_info':
        include_once 'managers/ocelot_info.php';
        break;


    case 'userclass':
        include_once 'managers/userclass_list.php';
        break;
    case 'privilege-edit':
        include_once 'managers/userclass_edit.php';
        break;
    case 'privilege-alter':
        include_once 'managers/userclass_alter.php';
        break;
    case 'privilege_matrix':
        include_once 'managers/privilege_matrix.php';
        break;

    case 'quick_ban':
        include_once 'managers/quick_ban.php';
        break;

    case 'rate_limit':
        include_once 'managers/rate_limit.php';
        break;
    case 'reaper':
        include_once 'managers/reaper.php';
        break;
    case 'referral_accounts':
        include_once 'managers/referral_accounts.php';
        break;
    case 'referral_alter':
        include_once 'managers/referral_alter.php';
        break;
    case 'referral_users':
        include_once 'managers/referral_users.php';
        break;

    case 'ssl_host':
        include_once 'managers/ssl_host.php';
        break;
    case 'staff_groups_alter':
        include_once 'managers/staff_groups_alter.php';
        break;
    case 'staff_groups':
        include_once 'managers/staff_groups_list.php';
        break;
    case 'stylesheets':
        include_once 'managers/stylesheets_list.php';
        break;

    case 'tags':
        include_once 'managers/tags.php';
        break;
    case 'tags_aliases':
        include_once 'managers/tags_aliases.php';
        break;
    case 'tags_official':
        include_once 'managers/tags_official.php';
        break;
    case 'tokens':
        include_once 'managers/tokens.php';
        break;
    case 'tor_node':
        include_once 'managers/tor_node.php';
        break;
    case 'torrent_report_edit':
        include_once 'managers/torrent_report_edit.php';
        break;
    case 'torrent_report_view':
        include_once 'managers/torrent_report_view.php';
        break;

    case 'whitelist':
        include_once 'managers/whitelist_list.php';
        break;
    case 'whitelist_alter':
        include_once 'managers/whitelist_alter.php';
        break;

    // Finances
    case 'donation_log':
        include_once 'finances/donation_log.php';
        break;
    case 'donor_rewards':
        include_once 'finances/donor_rewards.php';
        break;

    case 'payment_alter':
        include_once 'finances/payment_alter.php';
        break;

    case 'payment_list':
        include_once 'finances/payment_list.php';
        break;

    //Data
    case 'registration_log':
        include_once 'data/registration_log.php';
        break;

    case 'ratio_watch':
        include_once 'data/ratio_watch.php';
        break;

    case 'invite_pool':
        include_once 'data/invite_pool.php';
        break;
    case 'site_info':
        include_once 'data/site_info.php';
        break;

    case 'torrent_stats':
        include_once 'data/torrent_stats.php';
        break;

    case 'user_flow':
        include_once 'data/user_flow.php';
        break;

    case 'user_info':
        include_once 'data/user_info.php';
        break;

    case 'bonus_stats':
        include_once 'data/bonus_stats.php';
        break;

    case 'economic_stats':
        include_once 'data/economic_stats.php';
        break;

    case 'special_users':
        include_once 'data/special_users.php';
        break;

    case 'platform_usage':
        include_once 'data/platform_usage.php';
        break;

    // Development
    case 'analysis':
        include_once 'development/analysis.php';
        break;
    case 'analysis_list':
        include_once 'development/analysis_list.php';
        break;
    case 'bbcode_sandbox':
        if (!$Viewer->permitted('users_mod')) {
            error(403);
        }
        echo $Twig->render('admin/sandbox/bbcode.twig');
        break;
    case 'clear_cache':
        include_once 'development/clear_cache.php';
        break;
    case 'db-mysql':
        include_once 'development/mysql.php';
        break;
    case 'db-pg':
        include_once 'development/pg.php';
        break;
    case 'db_sandbox':
        include_once 'development/db_sandbox.php';
        break;
    case 'notification_sandbox':
        include_once 'development/notification.php';
        break;
    case 'process_info':
        include_once 'development/process_info.php';
        break;
    case 'referral_sandbox':
        include_once 'development/referral_sandbox.php';
        break;
    case 'service_stats':
        include_once 'development/service_stats.php';
        break;
    case 'site_options':
        include_once 'development/site_options.php';
        break;

    case 'periodic':
        $mode = $_REQUEST['mode'] ?? 'view';
        switch ($mode) {
            case 'enqueue':
            case 'view':
                include_once 'development/periodic_view.php';
                break;
            case 'detail':
                include_once 'development/periodic_detail.php';
                break;
            case 'stats':
                include_once 'development/periodic_stats.php';
                break;
            case 'edit':
                include_once 'development/periodic_edit.php';
                break;
            case 'alter':
                include_once 'development/periodic_alter.php';
                break;
            case 'run':
                include_once 'development/periodic_run.php';
                break;
        }
        break;

    //Services
    case 'get_host':
        include_once 'services/get_host.php';
        break;
    case 'get_cc':
        include_once 'services/get_cc.php';
        break;

    default:
        include_once 'tools.php';
}
