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
    include(SERVER_ROOT.'/sections/tools/tools.php');
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
        include(SERVER_ROOT.'/sections/tools/services/get_host.php');
        break;
    case 'get_cc':
        include(SERVER_ROOT.'/sections/tools/services/get_cc.php');
        break;
    //Managers
    case 'categories':
        include(SERVER_ROOT . '/sections/tools/managers/categories_list.php');
        break;

    case 'categories_alter':
        include(SERVER_ROOT . '/sections/tools/managers/categories_alter.php');
        break;

    case 'forum':
        include(SERVER_ROOT.'/sections/tools/managers/forum_list.php');
        break;

    case 'forum_alter':
        include(SERVER_ROOT.'/sections/tools/managers/forum_alter.php');
        break;

    case 'irc':
        include(SERVER_ROOT . '/sections/tools/managers/irc_list.php');
        break;

    case 'irc_alter':
        include(SERVER_ROOT . '/sections/tools/managers/irc_alter.php');
        break;

    case 'whitelist':
        include(SERVER_ROOT.'/sections/tools/managers/whitelist_list.php');
        break;

    case 'whitelist_alter':
        include(SERVER_ROOT.'/sections/tools/managers/whitelist_alter.php');
        break;

    case 'referral_accounts':
        include(SERVER_ROOT.'/sections/tools/managers/referral_accounts.php');
        break;

    case 'referral_alter':
        include(SERVER_ROOT.'/sections/tools/managers/referral_alter.php');
        break;

    case 'referral_users':
        include(SERVER_ROOT.'/sections/tools/managers/referral_users.php');
        break;

    case 'payment_alter':
        include(SERVER_ROOT.'/sections/tools/finances/payment_alter.php');
        break;

    case 'payment_list':
        include(SERVER_ROOT.'/sections/tools/finances/payment_list.php');
        break;

    case 'enable_requests':
        include(SERVER_ROOT.'/sections/tools/managers/enable_requests.php');
        break;
    case 'ajax_take_enable_request':
        if (FEATURE_EMAIL_REENABLE) {
            include(SERVER_ROOT.'/sections/tools/managers/ajax_take_enable_request.php');
        } else {
            // Prevent post requests to the ajax page
            header("Location: tools.php");
            die();
        }
        break;

    case 'login_watch':
        include(SERVER_ROOT.'/sections/tools/managers/login_watch.php');
        break;

    case 'recommend':
        include(SERVER_ROOT.'/sections/tools/managers/recommend_list.php');
        break;

    case 'recommend_add':
        include(SERVER_ROOT.'/sections/tools/managers/recommend_add.php');
        break;

    case 'recommend_alter':
        include(SERVER_ROOT.'/sections/tools/managers/recommend_alter.php');
        break;

    case 'recommend_restore':
        include(SERVER_ROOT.'/sections/tools/managers/recommend_restore.php');
        break;

    case 'email_blacklist':
        include(SERVER_ROOT.'/sections/tools/managers/email_blacklist.php');
        break;

    case 'email_blacklist_alter':
        include(SERVER_ROOT.'/sections/tools/managers/email_blacklist_alter.php');
        break;

    case 'email_blacklist_search':
        include(SERVER_ROOT.'/sections/tools/managers/email_blacklist_search.php');
        break;

    case 'dnu':
        include(SERVER_ROOT.'/sections/tools/managers/dnu_list.php');
        break;

    case 'dnu_alter':
        include(SERVER_ROOT.'/sections/tools/managers/dnu_alter.php');
        break;

    case 'editnews':
    case 'news':
        include(SERVER_ROOT.'/sections/tools/managers/news.php');
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
            INSERT INTO news (UserID, Title, Body, Time)
            VALUES (?, ?, ?, now())
        ", $LoggedUser['ID'], $_POST['title'], $_POST['body']);
        $Cache->delete_value('news_latest_id');
        $Cache->delete_value('news_latest_title');
        $Cache->delete_value('news');

        Notification::send_push(Notification::get_push_enabled_users(), $_POST['title'], $_POST['body'], site_url() . 'index.php', Notification::NEWS);

        header('Location: index.php');
        break;

    case 'bonus_points':
        include(SERVER_ROOT.'/sections/tools/managers/bonus_points.php');
        break;
    case 'tokens':
        include(SERVER_ROOT.'/sections/tools/managers/tokens.php');
        break;
    case 'multiple_freeleech':
        include(SERVER_ROOT.'/sections/tools/managers/multiple_freeleech.php');
        break;
    case 'ocelot':
        include(SERVER_ROOT.'/sections/tools/managers/ocelot.php');
        break;
    case 'ocelot_info':
        include(SERVER_ROOT.'/sections/tools/data/ocelot_info.php');
        break;
    case 'official_tags':
        include(SERVER_ROOT.'/sections/tools/managers/official_tags.php');
        break;
    case 'edit_tags':
        include(SERVER_ROOT.'/sections/tools/misc/tags.php');
        break;
    case 'tag_aliases':
        include(SERVER_ROOT.'/sections/tools/managers/tag_aliases.php');
        break;
    case 'label_aliases':
        include(SERVER_ROOT.'/sections/tools/managers/label_aliases.php');
        break;
    case 'change_log':
        include(SERVER_ROOT.'/sections/tools/managers/change_log.php');
        break;
    case 'global_notification':
        include(SERVER_ROOT.'/sections/tools/managers/global_notification.php');
        break;
    case 'take_global_notification':
        include(SERVER_ROOT.'/sections/tools/managers/take_global_notification.php');
        break;
    case 'permissions':
        if (!check_perms('admin_manage_permissions')) {
            error(403);
        }

        if (!empty($_REQUEST['id'])) {
            $Val->SetFields('name', true, 'string', 'You did not enter a valid name for this permission set.');
            $Val->SetFields('level', true, 'number', 'You did not enter a valid level for this permission set.');
            $_POST['maxcollages'] = (empty($_POST['maxcollages'])) ? 0 : $_POST['maxcollages'];
            $Val->SetFields('maxcollages', true, 'number', 'You did not enter a valid number of personal collages.');

            if (is_numeric($_REQUEST['id'])) {
                $DB->prepared_query("
                    SELECT p.ID, p.Name, p.Level, p.Secondary, p.PermittedForums, p.Values, p.DisplayStaff, p.StaffGroup, COUNT(u.ID)
                    FROM permissions AS p
                        LEFT JOIN users_main AS u ON u.PermissionID = p.ID
                    WHERE p.ID = ?
                    GROUP BY p.ID", $_REQUEST['id']);
                list($ID, $Name, $Level, $Secondary, $Forums, $Values, $DisplayStaff, $StaffGroup, $UserCount) = $DB->next_record(MYSQLI_NUM, [5]);

                if (!check_perms('admin_manage_permissions', $Level)) {
                    error(403);
                }
                $Values = unserialize($Values);
            }

            if (!empty($_POST['submit'])) {
                $Err = $Val->ValidateForm($_POST);

                if (!is_numeric($_REQUEST['id'])) {
                    $DB->prepared_query("
                        SELECT ID
                        FROM permissions
                        WHERE Level = ?", $_REQUEST['level']);
                    list($DupeCheck)=$DB->next_record();

                    if ($DupeCheck) {
                        $Err = 'There is already a permission class with that level.';
                    }
                }

                $Values = [];
                foreach ($_REQUEST as $Key => $Perms) {
                    if (substr($Key, 0, 5) == 'perm_') {
                        $Values[substr($Key, 5)] = (int)$Perms;
                    }
                }

                $Name = $_REQUEST['name'];
                $Level = $_REQUEST['level'];
                $Secondary = empty($_REQUEST['secondary']) ? 0 : 1;
                $Forums = $_REQUEST['forums'];
                $DisplayStaff = empty($_REQUEST['displaystaff']) ? '0' : '1';
                $StaffGroup = $_REQUEST['staffgroup'];
                if (!$StaffGroup) {
                    $StaffGroup = null;
                }
                $Values['MaxCollages'] = $_REQUEST['maxcollages'];

                if (!$Err) {
                    if (!is_numeric($_REQUEST['id'])) {
                        $DB->prepared_query("
                            INSERT INTO permissions (Level, Name, Secondary, PermittedForums, `Values`, DisplayStaff, StaffGroup)
                            VALUES (?, ?, ?, ?, ?, ?, ?)",
                            $Level, $Name, $Secondary, $Forums, serialize($Values), $DisplayStaff, $StaffGroup);
                    } else {
                        $DB->prepared_query("
                            UPDATE permissions
                            SET Level = ?,
                                Name = ?,
                                Secondary = ?,
                                PermittedForums = ?,
                                `Values` = ?,
                                DisplayStaff = ?,
                                StaffGroup = ?
                            WHERE ID = ?",
                            $Level, $Name, $Secondary, $Forums, serialize($Values), $DisplayStaff, $StaffGroup, $_REQUEST['id']);
                        $Cache->delete_value('perm_'.$_REQUEST['id']);
                        if ($Secondary) {
                            $DB->prepared_query("
                                SELECT DISTINCT UserID
                                FROM users_levels
                                WHERE PermissionID = ?", $_REQUEST['id']);
                            while ($UserID = $DB->next_record()) {
                                $Cache->delete_value("user_info_heavy_$UserID");
                            }
                        }
                    }
                    $Cache->delete_value('classes');
                    $Cache->delete_value('staff');
                } else {
                    error($Err);
                }
            }

            include(SERVER_ROOT.'/sections/tools/managers/permissions_alter.php');

        } else {
            if (!empty($_REQUEST['removeid'])) {
                $DB->prepared_query("
                    DELETE FROM permissions
                    WHERE ID = ?", $_REQUEST['removeid']);
                $DB->prepared_query("
                    SELECT UserID
                    FROM users_levels
                    WHERE PermissionID = ?", $_REQUEST['removeid']);
                while (list($UserID) = $DB->next_record()) {
                    $Cache->delete_value("user_info_$UserID");
                    $Cache->delete_value("user_info_heavy_$UserID");
                }
                $DB->prepared_query("
                    DELETE FROM users_levels
                    WHERE PermissionID = ?", $_REQUEST['removeid']);
                $DB->prepared_query("
                    SELECT ID
                    FROM users_main
                    WHERE PermissionID = ?", $_REQUEST['removeid']);
                while (list($UserID) = $DB->next_record()) {
                    $Cache->delete_value("user_info_$UserID");
                    $Cache->delete_value("user_info_heavy_$UserID");
                }
                $DB->prepared_query("
                    UPDATE users_main
                    SET PermissionID = ?
                    WHERE PermissionID = ?", USER, $_REQUEST['removeid']);

                $Cache->delete_value('classes');
            }

            include(SERVER_ROOT.'/sections/tools/managers/permissions_list.php');
        }
        break;
    case 'staff_groups_alter':
        include(SERVER_ROOT.'/sections/tools/managers/staff_groups_alter.php');
        break;
    case 'staff_groups':
        include(SERVER_ROOT.'/sections/tools/managers/staff_groups_list.php');
        break;
    case 'ip_ban':
        //TODO: Clean up DB table ip_bans.
        include(SERVER_ROOT.'/sections/tools/managers/bans.php');
        break;
    case 'quick_ban':
        include(SERVER_ROOT.'/sections/tools/misc/quick_ban.php');
        break;
    //Data
    case 'registration_log':
        include(SERVER_ROOT.'/sections/tools/data/registration_log.php');
        break;

    case 'prvlog':
        include(SERVER_ROOT.'/sections/tools/finances/btc_log.php');
        break;

    case 'bitcoin_unproc':
        include(SERVER_ROOT.'/sections/tools/finances/bitcoin_unproc.php');
        break;

    case 'bitcoin_balance':
        include(SERVER_ROOT.'/sections/tools/finances/bitcoin_balance.php');
        break;

    case 'donor_rewards':
        include(SERVER_ROOT.'/sections/tools/finances/donor_rewards.php');
        break;
    case 'upscale_pool':
        include(SERVER_ROOT.'/sections/tools/data/upscale_pool.php');
        break;

    case 'invite_pool':
        include(SERVER_ROOT.'/sections/tools/data/invite_pool.php');
        break;

    case 'torrent_stats':
        include(SERVER_ROOT.'/sections/tools/data/torrent_stats.php');
        break;

    case 'user_flow':
        include(SERVER_ROOT.'/sections/tools/data/user_flow.php');
        break;

    case 'economic_stats':
        include(SERVER_ROOT.'/sections/tools/data/economic_stats.php');
        break;

    case 'service_stats':
        include(SERVER_ROOT.'/sections/tools/development/service_stats.php');
        break;

    case 'database_specifics':
        include(SERVER_ROOT.'/sections/tools/data/database_specifics.php');
        break;

    case 'special_users':
        include(SERVER_ROOT.'/sections/tools/data/special_users.php');
        break;

    case 'platform_usage':
        include(SERVER_ROOT.'/sections/tools/data/platform_usage.php');
        break;
    //END Data

    //Misc
    case 'update_geoip':
        include(SERVER_ROOT.'/sections/tools/development/update_geoip.php');
        break;

    case 'dupe_ips':
        include(SERVER_ROOT.'/sections/tools/misc/dupe_ip.php');
        break;

    case 'clear_cache':
        include(SERVER_ROOT.'/sections/tools/development/clear_cache.php');
        break;

    case 'create_user':
        include(SERVER_ROOT.'/sections/tools/misc/create_user.php');
        break;

    case 'manipulate_tree':
        include(SERVER_ROOT.'/sections/tools/misc/manipulate_tree.php');
        break;

    case 'site_info':
        include(SERVER_ROOT.'/sections/tools/development/site_info.php');
        break;

    case 'site_options':
        include(SERVER_ROOT.'/sections/tools/development/site_options.php');
        break;

    case 'recommendations':
        include(SERVER_ROOT.'/sections/tools/misc/recommendations.php');
        break;

    case 'analysis':
        include(SERVER_ROOT.'/sections/tools/misc/analysis.php');
        break;

    case 'analysis_list':
        include(__DIR__.'/misc/analysis_list.php');
        break;

    case 'process_info':
        include(SERVER_ROOT.'/sections/tools/development/process_info.php');
        break;

    case 'rate_limit':
        include(__DIR__.'/managers/rate_limit.php');
        break;

    case 'rerender_gallery':
        include(SERVER_ROOT.'/sections/tools/development/rerender_gallery.php');
        break;

    case 'periodic':
        $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'view';
        switch ($mode) {
            case 'run_now':
            case 'view':
                include(SERVER_ROOT.'/sections/tools/development/periodic_view.php');
                break;
            case 'detail':
                include(SERVER_ROOT.'/sections/tools/development/periodic_detail.php');
                break;
            case 'stats':
                include(SERVER_ROOT.'/sections/tools/development/periodic_stats.php');
                break;
            case 'edit':
                include(SERVER_ROOT.'/sections/tools/development/periodic_edit.php');
                break;
            case 'alter':
                include(SERVER_ROOT.'/sections/tools/development/periodic_alter.php');
                break;
        }
        break;

    case 'public_sandbox':
        include(SERVER_ROOT.'/sections/tools/sandboxes/public_sandbox.php');
        break;

    case 'mod_sandbox':
        if (check_perms('users_mod')) {
            include(SERVER_ROOT.'/sections/tools/sandboxes/mod_sandbox.php');
        } else {
            error(403);
        }
        break;
    case 'bbcode_sandbox':
        include(SERVER_ROOT.'/sections/tools/sandboxes/bbcode_sandbox.php');
        break;
    case 'artist_importance_sandbox':
        include(SERVER_ROOT.'/sections/tools/sandboxes/artist_importance_sandbox.php');
        break;
    case 'db_sandbox':
        include(SERVER_ROOT.'/sections/tools/sandboxes/db_sandbox.php');
        break;
    case 'referral_sandbox':
        include(SERVER_ROOT.'/sections/tools/sandboxes/referral_sandbox.php');
        break;
    case 'calendar':
        include(SERVER_ROOT.'/sections/tools/managers/calendar.php');
        break;
    case 'get_calendar_event':
        include(SERVER_ROOT.'/sections/tools/managers/ajax_get_calendar_event.php');
        break;
    case 'take_calendar_event':
        include(SERVER_ROOT.'/sections/tools/managers/ajax_take_calendar_event.php');
        break;
    case 'stylesheets':
        include(SERVER_ROOT.'/sections/tools/managers/stylesheets_list.php');
        break;
    case 'mass_pm':
        include(SERVER_ROOT.'/sections/tools/managers/mass_pm.php');
        break;
    case 'take_mass_pm':
        include(SERVER_ROOT.'/sections/tools/managers/take_mass_pm.php');
        break;
    case 'monthalbum':
        include(SERVER_ROOT.'/sections/tools/misc/album_of_month.php');
        break;
    case 'vanityhouse':
        include(SERVER_ROOT.'/sections/tools/misc/vanity_house.php');
        break;
    case 'dbkey':
        include(SERVER_ROOT.'/sections/tools/managers/db_key.php');
        break;
    case 'navigation_alter':
        include(SERVER_ROOT.'/sections/tools/managers/navigation_alter.php');
        break;
    case 'navigation':
        include(SERVER_ROOT.'/sections/tools/managers/navigation_list.php');
        break;
    case 'forum_transitions':
        include(SERVER_ROOT.'/sections/tools/managers/forum_transitions_list.php');
        break;
    case 'forum_transitions_alter':
        include(SERVER_ROOT.'/sections/tools/managers/forum_transitions_alter.php');
        break;
    default:
        include(SERVER_ROOT.'/sections/tools/tools.php');
}
