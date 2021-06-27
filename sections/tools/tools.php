<?php
/* This file displays the list of available tools in the staff toolbox. */

if (!check_perms('users_mod')) {
    error(403);
}

function All(array $permlist) {
    foreach ($permlist as $p) {
        if (!check_perms($p)) {
            return false;
        }
    }
    return true;
}

function Any(array $permlist) {
    foreach ($permlist as $p) {
        if (check_perms($p)) {
            return true;
        }
    }
    return false;
}

/**
 * Used for rendering a single table row in the staff toolbox. The
 * $ToolsHTML variable is incrementally expanded with each function call
 * in a given subcontainer and gets reset at the beginning of each new
 * subcontainer.
 *
 * @param string $Title - the displayed name of the tool
 * @param string $URL - the relative URL of the tool
 * @param bool $HasPermission - whether the user has permission to view/use the tool
 * @param string $Tooltip - optional tooltip
 *
 */

function Item($Title, $URL, $HasPermission = false, $Tooltip = false) {
    return $HasPermission
        ? sprintf('<tr><td><a href="%s"%s>%s</a></td></tr>',
            $URL,
            ($Tooltip ? " class=\"tooltip\" title=\"$Tooltip\"" : ''),
            $Title
        )
        : '';
}

function Category($Title, array $Entries) {
    $html = '';
    foreach ($Entries as $e) {
        $html .= $e;
    }
    if (strlen($html)) {
?>
        <div class="permission_subcontainer">
            <table class="layout">
                <tr class="colhead"><td><?= $Title ?></td></tr>
                <?= $html ?>
            </table>
        </div>
<?php
    }
}

View::show_header('Staff Tools');
?>
<div class="permissions">
    <div class="toolbox_container">
    <!-- begin column 1 -->
<?php

Category('Administration', [
    Item('Applicant manager',        'apply.php?action=admin',            All(['admin_manage_applicants'])),
    Item('Auto-Enable requests',     'tools.php?action=enable_requests',  All(['users_mod']) && FEATURE_EMAIL_REENABLE),
    Item('Database encryption key',  'tools.php?action=dbkey',            All(['admin_site_debug'])),
    Item('Permissions manager',      'tools.php?action=permissions',      All(['admin_manage_permissions'])),
    Item('Privilege matrix',         'tools.php?action=privilege_matrix', All(['admin_manage_permissions'])),
    Item('Reports V1',               'reports.php',                       Any(['admin_reports', 'site_moderate_forums'])),
    Item('Staff page group manager', 'tools.php?action=staff_groups',     All(['admin_manage_permissions'])),
]);

Category('Announcements', [
    Item('Album of the Month',  'tools.php?action=monthalbum',          All(['users_mod'])),
    Item('Calendar',            'tools.php?action=calendar',            Calendar::can_view()),
    Item('Change log',          'tools.php?action=change_log',          All(['users_mod'])),
    Item('Global notification', 'tools.php?action=global_notification', All(['admin_global_notification'])),
    Item('Mass PM',             'tools.php?action=mass_pm',             All(['admin_global_notification'])),
    Item('News post',           'tools.php?action=news',                All(['admin_manage_news'])),
    Item('Vanity House',        'tools.php?action=vanityhouse',         All(['users_mod'])),
]);

Category('Rewards', [
    Item('Manage bonus points',         'tools.php?action=bonus_points',       All(['users_mod'])),
    Item('Manage freeleech tokens',     'tools.php?action=tokens',             All(['users_mod'])),
    Item('Freeleech torrents/collages', 'tools.php?action=multiple_freeleech', All(['users_mod'])),
]);

Category('Stylesheets', [
    Item('Stylesheet usage',          'tools.php?action=stylesheets',      All(['admin_manage_stylesheets'])),
]);

?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 2 -->
<?php

Category('User management', [
    Item('Create user',         'tools.php?action=create_user',       All(['admin_create_users'])),
    Item('Login watch',         'tools.php?action=login_watch',       All(['admin_login_watch'])),
    Item('Invite pool',         'tools.php?action=invite_pool',       All(['users_view_invites'])),
    Item('Invite tree manager', 'tools.php?action=manipulate_tree',   All(['users_mod'])),
    Item('Special users',       'tools.php?action=special_users',     All(['admin_manage_permissions'])),
    Item('Recovery',            'recovery.php?action=admin',          All(['admin_recovery'])),
    Item('Referral accounts',   'tools.php?action=referral_accounts', All(['admin_manage_referrals'])),
    Item('Referred users',      'tools.php?action=referral_users',    All(['admin_view_referrals'])),
    Item('Registration log',    'tools.php?action=registration_log',  All(['users_view_ips', 'users_view_email'])),
    Item('User flow',           'tools.php?action=user_flow',         All(['site_view_flow'])),
]);

Category('Community', [
    Item('Contest manager',         'contest.php?action=admin',           All(['admin_manage_contest'])),
    Item('Forum categories',        'tools.php?action=categories',        All(['admin_manage_forums'])),
    Item('Forum departments',       'tools.php?action=forum',             All(['admin_manage_forums'])),
    Item('Forum transitions',       'tools.php?action=forum_transitions', All(['admin_manage_forums'])),
    Item('Invite Sources',          'tools.php?action=invite_source',     All(['admin_manage_invite_source'])),
    Item('IRC manager',             'tools.php?action=irc',               All(['admin_manage_forums'])),
    Item('Navigation link manager', 'tools.php?action=navigation',        All(['admin_manage_navigation'])),
]);

?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 3 -->
<?php

Category('Torrents', [
    Item('Client whitelist',     'tools.php?action=whitelist',     All(['admin_whitelist'])),
    Item('"Do Not Upload" list', 'tools.php?action=dnu',           All(['admin_dnu'])),
    Item('Collage recovery',     'collages.php?action=recover',    All(['site_collages_recover'])),
    Item('Label aliases',        'tools.php?action=label_aliases', All(['users_mod'])),
    Item('Rate limiting',        'tools.php?action=rate_limit',    Any(['admin_rate_limit_view', 'admin_rate_limit_manage'])),
]);

Category('Tags', [
    Item('Batch tag editor',      'tools.php?action=tags',          All(['users_mod'])),
    Item('Tag aliases',           'tools.php?action=tags_aliases',  Any(['users_mod', 'site_tag_aliases_read'])),
    Item('Official tags manager', 'tools.php?action=tags_official', All(['users_mod'])),
]);

Category('External data', [
    Item('Email blacklist',        'tools.php?action=email_blacklist', All(['users_view_email'])),
    Item('IP address bans',        'tools.php?action=ip_ban',          All(['admin_manage_ipbans'])),
    Item('Duplicate IP addresses', 'tools.php?action=dupe_ips',        All(['users_view_ips'])),
    Item('Update GeoIP',           'tools.php?action=update_geoip',    All(['admin_update_geoip'])),
]);

Category('Finances', [
    Item('Bitcoin (balance)',     'tools.php?action=bitcoin_balance', All(['admin_donor_log'])),
    Item('Donation log',          'tools.php?action=donation_log',    All(['admin_donor_log'])),
    Item('Donor rewards',         'tools.php?action=donor_rewards',   All(['users_mod'])),
    Item('Payment dates',         'tools.php?action=payment_list',    Any(['admin_view_payments', 'admin_manage_payments'])),
]);

?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 4 -->
<?php

Category('Site Information', [
    Item('Bonus points stats',   'tools.php?action=bonus_stats',    All(['admin_bp_history'])),
    Item('Economic stats',       'tools.php?action=economic_stats', All(['site_view_flow'])),
    Item('Torrent stats',        'tools.php?action=torrent_stats',  All(['site_view_flow'])),
    Item('Ratio watch',          'tools.php?action=ratio_watch',    All(['site_view_flow'])),
    Item('OS and browser usage', 'tools.php?action=platform_usage', All(['site_debug'])),
    Item('Site info',            'tools.php?action=site_info',      All(['admin_site_debug'])),
    Item('Site options',         'tools.php?action=site_options',   All(['users_mod'])),
    Item('Tracker info',         'tools.php?action=ocelot_info',    All(['users_mod'])),
]);

Category('Developer Sandboxes', [
    Item('BBCode sandbox',       'tools.php?action=bbcode_sandbox',            All(['users_mod'])),
    Item('DB Sandbox',           'tools.php?action=db_sandbox',                All(['admin_site_debug'])),
    Item('Notification sandbox', 'tools.php?action=notification_sandbox',      All(['admin_view_notifications'])),
    Item('Referral sandbox',     'tools.php?action=referral_sandbox',          All(['site_debug', 'admin_manage_referrals'])),
]);

Category('Development', [
    Item('Cache/DB stats',        'tools.php?action=service_stats',            All(['site_debug'])),
    Item('Cache Inspector',       'tools.php?action=clear_cache',              All(['users_mod'])),
    Item('Database schema info',  'tools.php?action=database_specifics',       All(['site_database_specifics'])),
    Item('Error Analysis',        'tools.php?action=analysis_list',            All(['site_analysis'])),
    Item('PHP processes',         'tools.php?action=process_info',             All(['admin_site_debug'])),
    Item('Scheduler',             'tools.php?action=periodic&amp;mode=view',   All(['admin_periodic_task_view'])),
    Item('Scheduler (legacy)',    'schedule.php?auth='. $Viewer->auth(),       All(['admin_schedule'])),
]);

?>
    </div>
</div>
<?php
View::show_footer();
