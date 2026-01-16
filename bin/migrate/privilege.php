#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Gazelle;

require_once __DIR__ . '/../../lib/bootstrap.php';

/* The phinx migration will set up the default privileges
 * according to the initial installation. For a site in
 * production, the privileges of a userclass may have
 * evolved from that. This script removes the previous
 * userclass privileges (set by the migration) and replaces
 * them the values read from the database.
 *
 * If subsequent changes are made to the Postgres tables,
 * running this script again will WIPE THOSE OUT.
 */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

/**
 * @param array<string> $privilege
 */
function setUserclassHasPrivilege(string $name, array $privilege): void {
    echo "============== {$name} ==============\n"
        . implode("\n", array_map(fn ($p) => "'$p',", $privilege)), "\n";
    if ($privilege === []) {
        return;
    }
    $list = implode(',', array_map(fn ($p) => "('$p')", $privilege));

    $pg = new DB\Pg(PG_RW_DSN);
    $pg->prepared_query("
        insert into userclass_has_privilege (id_privilege, id_userclass)
        select p.id_privilege, uc.id_userclass
        from privilege p
        cross join userclass uc where uc.name = '$name'
            and  p.name in ($list)
    ");
}

/* Map old privileges to new names. Some new privileges are
 * introduced, that will in time replace the attribute flags
 * that revoke access to functionality (such as the the
 * "disable-avatar" user attribute).
 */

$mapPrivilege = [
    'admin_add_log'                => 'torrent-riplog-add',
    'admin_advanced_user_search'   => 'search-user-advanced',
    'admin_audit_edit'             => 'audit-edit',
    'admin_audit_view'             => 'audit-view',
    'admin_bp_history'             => 'view-bp-history',
    'admin_clear_cache'            => 'cache-edit',
    'admin_create_users'           => 'user-create',
    'admin_dnu'                    => 'dnu-edit',
    'admin_donor_log'              => 'donor-log-view',
    'admin_fl_history'             => 'freeleech-history',
    'admin_global_notification'    => 'global-notification',
    'admin_login_watch'            => 'login-watcher',
    'admin_manage_applicants'      => 'applicant-manage',
    'admin_manage_blog'            => 'blog-edit',
    'admin_manage_contest'         => 'contest-manage',
    'admin_manage_fls'             => 'fls-title',
    'admin_manage_forums'          => 'thread-transition-edit',
    'admin_manage_invite_source'   => 'invite-source-edit',
    'admin_manage_ipbans'          => 'ip-ban-manage',
    'admin_manage_navigation'      => 'user-menu-edit',
    'admin_manage_news'            => 'news-edit',
    'admin_manage_payments'        => 'payment-edit',
    'admin_manage_permissions'     => 'privilege-manage',
    'admin_manage_referrals'       => 'referral-edit',
    'admin_manage_stylesheets'     => 'stylesheet-edit',
    'admin_manage_user_fls'        => 'freeleech-user-edit',
    'admin_manage_wiki'            => 'wiki-remove',
    'admin_periodic_task_manage'   => 'schedule-task-edit',
    'admin_periodic_task_view'     => 'schedule-task-view',
    'admin_rate_limit_manage'      => 'download-limit-edit',
    'admin_rate_limit_view'        => 'download-limit-view',
    'admin_recovery'               => 'recovery',
    'admin_reports'                => 'torrent-report-manage',
    'admin_schedule'               => 'schedule-view',
    'admin_site_debug'             => 'site-admin-debug',
    'admin_staffpm_stats'          => 'staffpm-stats',
    'admin_view_notifications'     => 'upload-notifier-view',
    'admin_view_payments'          => 'payment-view',
    'admin_view_referrals'         => 'referral-view',
    'admin_whitelist'              => 'bt-client-edit',
    'artist_edit_vanityhouse'      => 'showcase-artist',
    'can_use_tor'                  => 'tor-access',
    'edit_unknowns'                => 'unknown-edition-edit',
    'forums_polls_create'          => 'forum-poll-create',
    'forums_polls_moderate'        => 'forum-poll-manage',
    'site_admin_forums'            => 'forum-post-history',
    'site_admin_requests'          => 'request-bounty-edit',
    'site_advanced_search'         => 'search-torrent-advanced',
    'site_advanced_top10'          => 'top10-advanced-view',
    'site_album_votes'             => 'torrent-vote',
    'site_analysis'                => 'error-log-view',
    'site_can_invite_always'       => 'invite-always',
    'site_collages_create'         => 'collage-general-create',
    'site_collages_delete'         => 'collage-remove',
    'site_collages_manage'         => 'collage-edit',
    'site_collages_personal'       => 'collage-personal-create',
    'site_collages_recover'        => 'collage-recover',
    'site_collages_renamepersonal' => 'collage-personal-rename',
    'site_collages_subscribe'      => 'collage-subscribe',
    'site_database_specifics'      => 'db-inspector',
    'site_debug'                   => 'site-debug',
    'site_delete_artist'           => 'artist-remove',
    'site_delete_tag'              => 'tag-delete',
    'site_disable_ip_history'      => 'no-ip-history',
    'site_edit_lineage'            => 'lineage-edit',
    'site_edit_requests'           => 'request-edit',
    'site_edit_wiki'               => 'edit-wiki',
    'site_forum_autosub'           => 'thread-autosub',
    'site_forum_post_delete'       => 'forum-post-delete',
    'site_leech'                   => 'torrent-leech',
    'site_make_bookmarks'          => 'bookmark-create',
    'site_moderate_forums'         => 'forum-moderator',
    'site_moderate_requests'       => 'request-full-edit',
    'site_search_many'             => 'search-many',
    'site_send_unlimited_invites'  => 'invite-unlimited',
    'site_submit_requests'         => 'request-create',
    'site_tag_aliases_read'        => 'tag-alias-view',
    'site_top10'                   => 'top10-view',
    'site_torrents_notify'         => 'torrent-notify',
    'site_unlimit_ajax'            => 'bypass-api-limit',
    'site_upload'                  => 'torrent-upload',
    'site_user_stats'              => 'view-user-stats',
    'site_view_flow'               => 'registration-flow',
    'site_view_full_log'           => 'search-full-log',
    'site_view_torrent_snatchlist' => 'view-snatchlist',
    'site_vote'                    => 'request-vote',
    'torrent_moderate'             => 'torrent-moderate',
    'torrents_add_artist'          => 'torrent-artist-add',
    'torrents_delete_fast'         => 'torrent-fast-delete',
    'torrents_delete'              => 'torrent-delete',
    'torrents_edit'                => 'torrent-edit',
    'torrents_edit_vanityhouse'    => 'showcase-torrent',
    'torrents_freeleech'           => 'freeleech-edit',
    'torrents_hide_dnu'            => 'hide-dnu',
    'users_auto_reports'           => 'auto-report',
    'users_delete_users'           => 'user-remove',
    'users_disable_any'            => 'user-action-disable',
    'users_disable_posts'          => 'forum-block-post',
    'users_disable_users'          => 'user-site-disable',
    'users_edit_avatars'           => 'avatar-edit',
    'users_edit_invites'           => 'invite-revoke',
    'users_edit_password'          => 'password-edit',
    'users_edit_profiles'          => 'profile-edit',
    'users_edit_reset_keys'        => 'announce-key-edit',
    'users_edit_titles'            => 'user-title-edit',
    'users_edit_usernames'         => 'username-edit',
    'users_give_donor'             => 'donor-edit',
    'users_invite_notes'           => 'invite-add-note',
    'users_linked_users'           => 'view-linked-user',
    'users_logout'                 => 'user-logout',
    'users_make_invisible'         => 'make-peer-invisible',
    'users_mod'                    => 'toolbox-view',
    'users_override_paranoia'      => 'paranoia-override',
    'users_view_email'             => 'view-email',
    'users_view_friends'           => 'view-friend',
    'users_view_invites'           => 'invite-view',
    'users_view_ips'               => 'view-ip',
    'users_view_keys'              => 'view-announce-keys',
    'users_view_seedleech'         => 'view-seed-leech',
    'users_view_uploaded'          => 'view-uploaded',
    'users_warn'                   => 'warn',
    'view_last_seen'               => 'view-last-seen',
    'zip_downloader'               => 'zip-collector',
];

$newPrivilege = [
    'access-irc',
    'acquire-bp',
    'collage-personal-create',
    'forum-access',
    'forum-can-post',
    'send-pm',
    'set-avatar',
    'tag-create',
];

// new privileges, not automatically granted
//  edit-viewable-wiki
//  feature-album
//  freeleech-token-edit
//  invite-create
//  promote-to-legend
//  promote-to-staff
//  torrent-moderate
//  tracker-edit
//  tracker-view

$obsolete = [
    'admin_freeleech',
    'admin_manage_polls',
    'admin_tracker',
    'site_archive_ajax',
    'site_proxy_images',
    'torrents_search_fast',
    'users_edit_own_ratio',
    'users_edit_ratio',
    'users_edit_watch_hours',
    'users_reset_own_keys',
];

$db = DB::DB();
$db->prepared_query("
    SELECT Level, Secondary, Name, `Values` FROM permissions ORDER BY Level
");
foreach ($db->to_array(false, MYSQLI_ASSOC) as $userclass) {
    $privilege = $userclass['Secondary'] ? [] : $newPrivilege;
    if ($userclass['Level'] >= 150 && !$userclass['Secondary']) {
        $privilege[] = 'invite-create';
    }
    if ($userclass['Level'] >= 800) {
        $privilege[] = 'edit-viewable-wiki';
        $privilege[] = 'torrent-moderate';
    }
    if ($userclass['Level'] === 1000) {
        $privilege = array_merge(
            $privilege,
            [
                'feature-album',
                'freeleech-token-edit',
                'promote-to-legend',
                'promote-to-staff',
                'tracker-edit',
                'tracker-view',
            ]
        );
    }
    foreach (array_keys(unserialize($userclass['Values'])) as $p) {
        if (isset($mapPrivilege[$p])) {
            $privilege[] = $mapPrivilege[$p];
        } elseif (in_array($p, $obsolete)) {
            continue;
        } else {
            echo "unknown permission $p for level {$userclass['Level']}\n";
        }
    }
    sort($privilege);
    setUserclassHasPrivilege($userclass['Name'], $privilege);
}
