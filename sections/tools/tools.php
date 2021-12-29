<?php
/* This file displays the list of available tools in the staff toolbox. */

if (!$Viewer->permitted('users_mod')) {
    error(403);
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
    Item('Applicant manager',        'apply.php?action=admin',            $Viewer->permitted('admin_manage_applicants')),
    Item('Auto-Enable requests',     'tools.php?action=enable_requests',  FEATURE_EMAIL_REENABLE),
    Item('Database encryption key',  'tools.php?action=dbkey',            $Viewer->permitted('admin_site_debug')),
    Item('Permissions manager',      'tools.php?action=permissions',      $Viewer->permitted('admin_manage_permissions')),
    Item('Privilege matrix',         'tools.php?action=privilege_matrix', $Viewer->permitted('admin_manage_permissions')),
    Item('Reports V1',               'reports.php',                       $Viewer->permittedAny('admin_reports', 'site_moderate_forums')),
    Item('Staff page group manager', 'tools.php?action=staff_groups',     $Viewer->permitted('admin_manage_permissions')),
]);

Category('Announcements', [
    Item('Album of the Month',  'tools.php?action=monthalbum',          $Viewer->permitted('admin_freeleech')),
    Item('Change log',          'tools.php?action=change_log',          true),
    Item('Global notification', 'tools.php?action=global_notification', $Viewer->permitted('admin_global_notification')),
    Item('Mass PM',             'tools.php?action=mass_pm',             $Viewer->permitted('admin_global_notification')),
    Item('News post',           'tools.php?action=news',                $Viewer->permitted('admin_manage_news')),
    Item('Vanity House',        'tools.php?action=vanityhouse',         $Viewer->permitted('admin_freeleech')),
]);

Category('Rewards', [
    Item('Manage bonus points',         'tools.php?action=bonus_points',       $Viewer->permitted('admin_bp_history')),
    Item('Manage freeleech tokens',     'tools.php?action=tokens',             $Viewer->permitted('admin_freeleech')),
    Item('Freeleech torrents/collages', 'tools.php?action=multiple_freeleech', $Viewer->permitted('admin_freeleech')),
]);
?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 2 -->
<?php

Category('User management', [
    Item('Create user',         'tools.php?action=create_user',       $Viewer->permitted('admin_create_users')),
    Item('Login watch',         'tools.php?action=login_watch',       $Viewer->permitted('admin_login_watch')),
    Item('Invite pool',         'tools.php?action=invite_pool',       $Viewer->permitted('users_view_invites')),
    Item('Invite tree manager', 'tools.php?action=manipulate_tree',   true),
    Item('Special users',       'tools.php?action=special_users',     $Viewer->permitted('admin_manage_permissions')),
    Item('Recovery',            'recovery.php?action=admin',          $Viewer->permitted('admin_recovery')),
    Item('Referral accounts',   'tools.php?action=referral_accounts', $Viewer->permitted('admin_manage_referrals') && OPEN_EXTERNAL_REFERRALS),
    Item('Referred users',      'tools.php?action=referral_users',    $Viewer->permitted('admin_view_referrals')),
    Item('Registration log',    'tools.php?action=registration_log',  $Viewer->permitted('users_view_email')),
    Item('User flow',           'tools.php?action=user_flow',         $Viewer->permitted('site_view_flow')),
]);

Category('Community', [
    Item('Contest manager',         'contest.php?action=admin',           $Viewer->permitted('admin_manage_contest')),
    Item('Forum categories',        'tools.php?action=categories',        $Viewer->permitted('admin_manage_forums')),
    Item('Forum departments',       'tools.php?action=forum',             $Viewer->permitted('admin_manage_forums')),
    Item('Forum transitions',       'tools.php?action=forum_transitions', $Viewer->permitted('admin_manage_forums')),
    Item('Invite Sources',          'tools.php?action=invite_source',     $Viewer->permitted('admin_manage_invite_source')),
    Item('IRC manager',             'tools.php?action=irc',               $Viewer->permitted('admin_manage_forums')),
    Item('Navigation link manager', 'tools.php?action=navigation',        $Viewer->permitted('admin_manage_navigation')),
    Item('Stylesheet usage',        'tools.php?action=stylesheets',       $Viewer->permitted('admin_manage_stylesheets')),
]);

?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 3 -->
<?php

Category('Torrents', [
    Item('Client whitelist',     'tools.php?action=whitelist',     $Viewer->permitted('admin_whitelist')),
    Item('"Do Not Upload" list', 'tools.php?action=dnu',           $Viewer->permitted('admin_dnu')),
    Item('Collage recovery',     'collages.php?action=recover',    $Viewer->permitted('site_collages_recover')),
    Item('Label aliases',        'tools.php?action=label_aliases', true),
    Item('Rate limiting',        'tools.php?action=rate_limit',    $Viewer->permittedAny('admin_rate_limit_view', 'admin_rate_limit_manage')),
]);

Category('Tags', [
    Item('Batch tag editor',      'tools.php?action=tags',          true),
    Item('Tag aliases',           'tools.php?action=tags_aliases',  true),
    Item('Official tags manager', 'tools.php?action=tags_official', true),
]);

Category('External data', [
    Item('Email blacklist',        'tools.php?action=email_blacklist', $Viewer->permitted('users_view_email')),
    Item('IP address bans',        'tools.php?action=ip_ban',          $Viewer->permitted('admin_manage_ipbans')),
    Item('IP bulk search',         'tools.php?action=ip_search',       $Viewer->permitted('users_view_ips')),
    Item('Duplicate IP addresses', 'tools.php?action=dupe_ips',        $Viewer->permitted('users_view_ips')),
]);

Category('Finances', [
    Item('Donation log',          'tools.php?action=donation_log',    $Viewer->permitted('admin_donor_log')),
    Item('Donor rewards',         'tools.php?action=donor_rewards',   true),
    Item('Payment dates',         'tools.php?action=payment_list',    $Viewer->permitted('admin_view_payments')),
]);

?>
    </div>
    <div class="toolbox_container">
    <!-- begin column 4 -->
<?php

Category('Site Information', [
    Item('Bonus points stats',   'tools.php?action=bonus_stats',    $Viewer->permitted('admin_bp_history')),
    Item('Economic stats',       'tools.php?action=economic_stats', $Viewer->permitted('site_view_flow')),
    Item('Torrent stats',        'tools.php?action=torrent_stats',  $Viewer->permitted('site_view_flow')),
    Item('Ratio watch',          'tools.php?action=ratio_watch',    $Viewer->permitted('site_view_flow')),
    Item('OS and browser usage', 'tools.php?action=platform_usage', $Viewer->permitted('site_debug')),
    Item('Site info',            'tools.php?action=site_info',      $Viewer->permitted('admin_site_debug')),
    Item('Site options',         'tools.php?action=site_options',   true),
    Item('Tracker info',         'tools.php?action=ocelot_info',    true),
]);

Category('Developer Sandboxes', [
    Item('BBCode sandbox',       'tools.php?action=bbcode_sandbox',            true),
    Item('DB Sandbox',           'tools.php?action=db_sandbox',                $Viewer->permitted('admin_site_debug')),
    Item('Notification sandbox', 'tools.php?action=notification_sandbox',      $Viewer->permitted('admin_view_notifications')),
    Item('Referral sandbox',     'tools.php?action=referral_sandbox',          $Viewer->permitted('admin_manage_referrals') && OPEN_EXTERNAL_REFERRALS),
]);

Category('Development', [
    Item('Cache/DB stats',        'tools.php?action=service_stats',            $Viewer->permitted('site_debug')),
    Item('Cache Inspector',       'tools.php?action=clear_cache',              $Viewer->permitted('site_debug')),
    Item('Database schema info',  'tools.php?action=database_specifics',       $Viewer->permitted('site_database_specifics')),
    Item('Error Analysis',        'tools.php?action=analysis_list',            $Viewer->permitted('site_analysis')),
    Item('PHP processes',         'tools.php?action=process_info',             $Viewer->permitted('admin_site_debug')),
    Item('Scheduler',             'tools.php?action=periodic&amp;mode=view',   $Viewer->permitted('admin_periodic_task_view')),
    Item('Scheduler (legacy)',    'schedule.php?auth='. $Viewer->auth(),       $Viewer->permitted('admin_schedule')),
]);

?>
    </div>
</div>
<?php
View::show_footer();
