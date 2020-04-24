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
    <div class="permission_container">
    <!-- begin left column -->
<?php

Category('Administration', [
    Item('Permissions manager',      'tools.php?action=permissions',     All(['admin_manage_permissions'])),
    Item('Staff page group manager', 'tools.php?action=staff_groups',    All(['admin_manage_permissions'])),
    Item('Torrent client whitelist', 'tools.php?action=whitelist',       All(['admin_whitelist'])),
    Item('Database encryption key',  'tools.php?action=dbkey',           All(['admin_site_debug'])),
    Item('Auto-Enable requests',     'tools.php?action=enable_requests', All(['users_mod']) && FEATURE_EMAIL_REENABLE),
    Item('Login watch',              'tools.php?action=login_watch',     All(['admin_login_watch'])),
    Item('Reports V1',               'reports.php',                      Any(['admin_reports', 'site_moderate_forums'])),
]);

Category('Announcements', [
    Item('News post',           'tools.php?action=news',                All(['admin_manage_news'])),
    Item('Global notification', 'tools.php?action=global_notification', All(['users_mod'])),
    Item('Mass PM',             'tools.php?action=mass_pm',             All(['users_mod'])),
    Item('Change log',          'tools.php?action=change_log',          All(['users_mod'])),
    Item('Calendar',            'tools.php?action=calendar',            Calendar::can_view()),
    Item('Vanity House',        'tools.php?action=vanityhouse',         All(['users_mod'])),
    Item('Album of the Month',  'tools.php?action=monthalbum',          All(['users_mod'])),
]);

Category('Community', [
    Item('Category manager',         'tools.php?action=categories',        All(['admin_manage_forums'])),
    Item('Contest manager',          'contest.php?action=admin',           All(['admin_manage_contest'])),
    Item('Forum manager',            'tools.php?action=forum',             All(['admin_manage_forums'])),
    Item('Forum transition manager', 'tools.php?action=forum_transitions', All(['admin_manage_forums'])),
    Item('Navigation link manager',  'tools.php?action=navigation',        All(['admin_manage_navigation'])),
    Item('IRC manager',              'tools.php?action=irc',               All(['admin_manage_forums'])),
]);

Category('Stylesheets', [
    Item('Stylesheet usage',          'tools.php?action=stylesheets',      All(['admin_manage_stylesheets'])),
    Item('Render stylesheet gallery', 'tools.php?action=rerender_gallery', Any(['site_debug', 'users_mod'])),
]);

?>
    <!-- end left column -->
    </div>
    <div class="permission_container">
    <!-- begin middle column -->
<?php

Category('User management', [
    Item('Create user',        'tools.php?action=create_user',       All(['admin_create_users'])),
    Item('Special users',      'tools.php?action=special_users',     All(['admin_manage_permissions'])),
    Item('Referral accounts',  'tools.php?action=referral_accounts', All(['admin_manage_referrals'])),
    Item('Referred users',     'tools.php?action=referral_users',    All(['admin_view_referrals'])),
    Item('User recovery',      'recovery.php?action=admin',          All(['admin_recovery'])),
    Item('User flow',          'tools.php?action=user_flow',         All(['site_view_flow'])),
    Item('Registration log',   'tools.php?action=registration_log',  All(['users_view_ips', 'users_view_email'])),
    Item('Invite pool',        'tools.php?action=invite_pool',       All(['users_view_invites'])),
    Item('Manage invite tree', 'tools.php?action=manipulate_tree',   All(['users_mod'])),
]);

Category('Rewards', [
    Item('Manage bonus points',         'tools.php?action=bonus_points',       All(['users_mod'])),
    Item('Freeleech torrents/collages', 'tools.php?action=multiple_freeleech', All(['users_mod'])),
    Item('Manage freeleech tokens',     'tools.php?action=tokens',             All(['users_mod'])),
]);

Category('Managers', [
    Item('IP address bans',        'tools.php?action=ip_ban',          All(['admin_manage_ipbans'])),
    Item('Duplicate IP addresses', 'tools.php?action=dupe_ips',        All(['users_view_ips'])),
    Item('Email blacklist',        'tools.php?action=email_blacklist', All(['users_view_email'])),
]);

Category('Torrents', [
    Item('Rate limiting',        'tools.php?action=rate_limit',    Any(['admin_rate_limit_view', 'admin_rate_limit_manage'])),
    Item('Recommended torrents', 'tools.php?action=recommend',     Any(['site_recommend_own', 'site_manage_recommendations'])),
    Item('Collage recovery',     'collages.php?action=recover',    All(['site_collages_recover'])),
    Item('"Do Not Upload" list', 'tools.php?action=dnu',           All(['admin_dnu'])),
    Item('Label aliases',        'tools.php?action=label_aliases', All(['users_mod'])),
]);

Category('Tags', [
    Item('Tag aliases',           'tools.php?action=tag_aliases',   All(['users_mod'])),
    Item('Batch tag editor',      'tools.php?action=edit_tags',     All(['users_mod'])),
    Item('Official tags manager', 'tools.php?action=official_tags', All(['users_mod'])),
]);

?>
    <!-- end middle column -->
    </div>
    <div class="permission_container">
    <!-- begin right column -->
<?php

Category('Site Information', [
    Item('Economic stats',       'tools.php?action=economic_stats', All(['site_view_flow'])),
    Item('Torrent stats',        'tools.php?action=torrent_stats',  All(['site_view_flow'])),
    Item('Ratio watch',          'tools.php?action=upscale_pool',   All(['site_view_flow'])),
    Item('OS and browser usage', 'tools.php?action=platform_usage', All(['site_debug'])),
]);

Category('Finances', [
    Item('Bitcoin (balance)',     'tools.php?action=bitcoin_balance', All(['admin_donor_log'])),
    Item('Bitcoin (unprocessed)', 'tools.php?action=bitcoin_unproc',  All(['admin_donor_log'])),
    Item('Donation log',          'tools.php?action=donation_log',    All(['admin_donor_log'])),
    Item('Donor rewards',         'tools.php?action=donor_rewards',   All(['users_mod'])),
    Item('Payment dates',         'tools.php?action=payment_list',    Any(['admin_view_payments', 'admin_manage_payments'])),
]);

Category('Developer Sandboxes', [
    Item('Artist Importance', 'tools.php?action=artist_importance_sandbox', All(['users_mod'])),
    Item('BBCode sandbox',    'tools.php?action=bbcode_sandbox',            All(['users_mod'])),
    Item('DB Sandbox',        'tools.php?action=db_sandbox',                All(['admin_site_debug'])),
    Item('Referral sandbox',  'tools.php?action=referral_sandbox',          All(['site_debug', 'admin_manage_referrals'])),
]);

Category('Development', [
    Item('Cache key management',  'tools.php?action=clear_cache',              All(['users_mod'])),
    Item('Database info',         'tools.php?action=database_specifics',       All(['site_database_specifics'])),
    Item('PHP processes',         'tools.php?action=process_info',             All(['site_debug'])),
    Item('Service stats',         'tools.php?action=service_stats',            All(['site_debug'])),
    Item('Error Analysis',        'tools.php?action=analysis_list',            All(['site_analysis'])),
    Item('Site info',             'tools.php?action=site_info',                All(['admin_site_debug'])),
    Item('Site options',          'tools.php?action=site_options',             All(['users_mod'])),
    Item('Scheduler',             'tools.php?action=periodic&amp;mode=view',   All(['admin_periodic_task_view'])),
    Item('Scheduler (legacy)',    'schedule.php?auth='.$LoggedUser['AuthKey'], All(['admin_schedule'])),
    Item('Tracker info',          'tools.php?action=ocelot_info',              All(['users_mod'])),
    Item('Update GeoIP',          'tools.php?action=update_geoip',             All(['admin_update_geoip'])),
]);

?>
    <!-- end right column -->
    </div>
</div>
<?php
View::show_footer();
