<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

switch ($_REQUEST['action'] ?? '') {
    //Managers
    case 'asn_search':
        require __DIR__ . '/managers/asn_search.php';
        break;
    case 'geoip_lookup':
        require __DIR__ . '/managers/geoip_lookup.php';
        break;
    case 'audit_trail':
        require __DIR__ . '/managers/audit_trail.php';
        break;

    case 'bonus_points':
        require __DIR__ . '/managers/bonus_points.php';
        break;

    case 'categories':
        require __DIR__ . '/managers/categories_list.php';
        break;
    case 'categories_alter':
        require __DIR__ . '/managers/categories_alter.php';
        break;
    case 'change_log':
        require __DIR__ . '/managers/change_log.php';
        break;
    case 'create_user':
        require __DIR__ . '/managers/create_user.php';
        break;
    case 'custom_pm':
        require __DIR__ . '/managers/custom_pm.php';
        break;

    case 'dbkey':
        require __DIR__ . '/managers/db_key.php';
        break;
    case 'dnu':
        require __DIR__ . '/managers/dnu_list.php';
        break;
    case 'dnu_alter':
        require __DIR__ . '/managers/dnu_alter.php';
        break;

    case 'email_blacklist':
        require __DIR__ . '/managers/email_blacklist.php';
        break;
    case 'email_blacklist_alter':
        require __DIR__ . '/managers/email_blacklist_alter.php';
        break;
    case 'email_search':
        require __DIR__ . '/managers/email_search.php';
        break;
    case 'enable_requests':
        require __DIR__ . '/managers/enable_requests.php';
        break;
    case 'ajax_take_enable_request':
        if (FEATURE_EMAIL_REENABLE) {
            require __DIR__ . '/managers/ajax_take_enable_request.php';
        } else {
            // Prevent post requests to the ajax page
            header("Location: tools.php");
        }
        break;

    case 'featured_album':
        require __DIR__ . '/managers/featured_album.php';
        break;
    case 'forum':
        require __DIR__ . '/managers/forum_list.php';
        break;
    case 'forum_alter':
        require __DIR__ . '/managers/forum_alter.php';
        break;
    case 'forum_transitions':
        require __DIR__ . '/managers/forum_transitions_list.php';
        break;
    case 'forum_transitions_alter':
        require __DIR__ . '/managers/forum_transitions_alter.php';
        break;

    case 'gift-event':
        require __DIR__ . '/managers/gift-event.php';
        break;
    case 'global_notification':
        require __DIR__ . '/managers/global_notification.php';
        break;

    case 'invite_source':
        require __DIR__ . '/managers/invite_source.php';
        break;
    case 'invite_source_config':
        require __DIR__ . '/managers/invite_source_config.php';
        break;
    case 'ip_ban':
        require __DIR__ . '/managers/bans.php';
        break;
    case 'ip-ban-edit':
        require __DIR__ . '/managers/ban-edit.php';
        break;
    case 'ip_search':
        require __DIR__ . '/managers/ip_search.php';
        break;
    case 'bulk_search':
        require __DIR__ . '/managers/bulk_search.php';
        break;

    case 'login_watch':
        require __DIR__ . '/managers/login_watch.php';
        break;

    case 'manipulate_tree':
        require __DIR__ . '/managers/manipulate_tree.php';
        break;
    case 'mass_pm':
        require __DIR__ . '/managers/mass_pm.php';
        break;
    case 'take_mass_pm':
        require __DIR__ . '/managers/take_mass_pm.php';
        break;

    case 'navigation_alter':
        require __DIR__ . '/managers/navigation_alter.php';
        break;
    case 'navigation':
        require __DIR__ . '/managers/navigation_list.php';
        break;

    case 'news':
    case 'deletenews':
    case 'editnews':
    case 'takeeditnews':
    case 'takenewnews':
        require __DIR__ . '/managers/news.php';
        break;

    case 'ocelot':
        // this is the callback for ocelot
        require __DIR__ . '/managers/ocelot.php';
        break;
    case 'ocelot_info':
        require __DIR__ . '/managers/ocelot_info.php';
        break;


    case 'userclass':
        require __DIR__ . '/managers/userclass_list.php';
        break;
    case 'privilege-edit':
        require __DIR__ . '/managers/userclass_edit.php';
        break;
    case 'privilege-alter':
        require __DIR__ . '/managers/userclass_alter.php';
        break;
    case 'privilege-audit':
        require __DIR__ . '/managers/privilege_audit.php';
        break;
    case 'privilege_matrix':
        require __DIR__ . '/managers/privilege_matrix.php';
        break;

    case 'quick_ban':
        require __DIR__ . '/managers/quick_ban.php';
        break;

    case 'rate_limit':
        require __DIR__ . '/managers/rate_limit.php';
        break;
    case 'reaper':
        require __DIR__ . '/managers/reaper.php';
        break;
    case 'referral_accounts':
        require __DIR__ . '/managers/referral_accounts.php';
        break;
    case 'referral_alter':
        require __DIR__ . '/managers/referral_alter.php';
        break;
    case 'referral_users':
        require __DIR__ . '/managers/referral_users.php';
        break;

    case 'ssl_host':
        require __DIR__ . '/managers/ssl_host.php';
        break;
    case 'staff_groups_alter':
        require __DIR__ . '/managers/staff_groups_alter.php';
        break;
    case 'staff_groups':
        require __DIR__ . '/managers/staff_groups_list.php';
        break;
    case 'stylesheets':
        require __DIR__ . '/managers/stylesheets_list.php';
        break;

    case 'tags':
        require __DIR__ . '/managers/tags.php';
        break;
    case 'tags_aliases':
        require __DIR__ . '/managers/tags_aliases.php';
        break;
    case 'tags_official':
        require __DIR__ . '/managers/tags_official.php';
        break;
    case 'tag-reject':
        require __DIR__ . '/managers/tag-reject.php';
        break;
    case 'tokens':
        require __DIR__ . '/managers/tokens.php';
        break;
    case 'tor_node':
        require __DIR__ . '/managers/tor_node.php';
        break;
    case 'torrent_report_edit':
        require __DIR__ . '/managers/torrent_report_edit.php';
        break;
    case 'torrent_report_view':
        require __DIR__ . '/managers/torrent_report_view.php';
        break;

    case 'whitelist':
        require __DIR__ . '/managers/whitelist_list.php';
        break;
    case 'whitelist_alter':
        require __DIR__ . '/managers/whitelist_alter.php';
        break;

    // Finances
    case 'donation_log':
        require __DIR__ . '/finances/donation_log.php';
        break;
    case 'donor_rewards':
        require __DIR__ . '/finances/donor_rewards.php';
        break;

    case 'payment_alter':
        require __DIR__ . '/finances/payment_alter.php';
        break;

    case 'payment_list':
        require __DIR__ . '/finances/payment_list.php';
        break;

    //Data
    case 'recruitment-history':
        require __DIR__ . '/data/recruitment-history.php';
        break;

    case 'registration_log':
        require __DIR__ . '/data/registration_log.php';
        break;

    case 'ratio_watch':
        require __DIR__ . '/data/ratio_watch.php';
        break;

    case 'recently_disabled':
        require __DIR__ . '/data/recently_disabled.php';
        break;

    case 'invite_pool':
        require __DIR__ . '/data/invite_pool.php';
        break;
    case 'site_info':
        require __DIR__ . '/data/site_info.php';
        break;

    case 'torrent_stats':
        require __DIR__ . '/data/torrent_stats.php';
        break;

    case 'user_flow':
        require __DIR__ . '/data/user_flow.php';
        break;

    case 'user_info':
        require __DIR__ . '/data/user_info.php';
        break;

    case 'bonus_stats':
        require __DIR__ . '/data/bonus_stats.php';
        break;

    case 'economic_stats':
        require __DIR__ . '/data/economic_stats.php';
        break;

    case 'special_users':
        require __DIR__ . '/data/special_users.php';
        break;

    case 'platform_usage':
        require __DIR__ . '/data/platform_usage.php';
        break;

    // Development
    case 'analysis':
        require __DIR__ . '/development/analysis.php';
        break;
    case 'analysis_list':
        require __DIR__ . '/development/analysis_list.php';
        break;
    case 'bbcode_sandbox':
        if (!$Viewer->permitted('users_mod')) {
            Error403::error();
        }
        echo $Twig->render('admin/sandbox/bbcode.twig');
        break;
    case 'clear_cache':
        require __DIR__ . '/development/clear_cache.php';
        break;
    case 'db-mysql':
        require __DIR__ . '/development/mysql.php';
        break;
    case 'db-pg':
        require __DIR__ . '/development/pg.php';
        break;
    case 'db_sandbox':
        require __DIR__ . '/development/db_sandbox.php';
        break;
    case 'notification_sandbox':
        require __DIR__ . '/development/notification.php';
        break;
    case 'process_info':
        require __DIR__ . '/development/process_info.php';
        break;
    case 'referral_sandbox':
        require __DIR__ . '/development/referral_sandbox.php';
        break;
    case 'service_stats':
        require __DIR__ . '/development/service_stats.php';
        break;
    case 'site_options':
        require __DIR__ . '/development/site_options.php';
        break;

    case 'periodic':
        $mode = $_REQUEST['mode'] ?? 'view';
        switch ($mode) {
            case 'enqueue':
            case 'view':
                require __DIR__ . '/development/periodic_view.php';
                break;
            case 'detail':
                require __DIR__ . '/development/periodic_detail.php';
                break;
            case 'stats':
                require __DIR__ . '/development/periodic_stats.php';
                break;
            case 'edit':
                require __DIR__ . '/development/periodic_edit.php';
                break;
            case 'alter':
                require __DIR__ . '/development/periodic_alter.php';
                break;
            case 'run':
                require __DIR__ . '/development/periodic_run.php';
                break;
        }
        break;

    //Services
    case 'get_host':
        require __DIR__ . '/services/get_host.php';
        break;
    case 'get_cc':
        require __DIR__ . '/services/get_cc.php';
        break;

    default:
        require __DIR__ . '/tools.php';
}
