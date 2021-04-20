<?php
class Permissions {
    public static function list() {
        return [
            'site_leech' => 'Can leech (Does this work?)',
            'site_upload' => 'Upload torrent access',
            'site_vote' => 'Request vote access',
            'site_submit_requests' => 'Request create access',
            'site_advanced_search' => 'Advanced search access',
            'site_top10' => 'Top 10 access',
            'site_advanced_top10' => 'Advanced Top 10 access',
            'site_album_votes' => 'Voting for favorite torrents',
            'site_torrents_notify' => 'Notifications access',
            'site_collages_create' => 'Collage create access',
            'site_collages_manage' => 'Collage manage access',
            'site_collages_delete' => 'Collage delete access',
            'site_collages_subscribe' => 'Collage subscription access',
            'site_collages_personal' => 'Can have a personal collage',
            'site_collages_renamepersonal' => 'Can rename own personal collages',
            'site_make_bookmarks' => 'Bookmarks access',
            'site_edit_wiki' => 'Wiki edit access',
            'users_view_invites' => 'Can view who user has invited',
            'site_send_unlimited_invites' => 'Unlimited invites up to maximum user count',
            'site_can_invite_always' => 'Can invite beyond maximum user count',
            'users_invite_notes' => 'Can add a staff note when inviting someone',
            'users_edit_invites' => 'Can edit invite numbers and cancel sent invites',
            'site_moderate_requests' => 'Request moderation access',
            'site_admin_requests' => 'Edit request bounties',
            'site_delete_artist' => 'Can delete artists (must be able to delete torrents+requests)',
            'site_moderate_forums' => 'Forum moderation access',
            'site_forum_post_delete' => 'Can hard delete forum posts',
            'site_admin_forums' => 'Forum administrator access',
            'site_view_flow' => 'Can view stats and data pools',
            'site_view_full_log' => 'Can view old log entries',
            'site_view_torrent_snatchlist' => 'Can view torrent snatch lists',
            'site_delete_tag' => 'Can delete tags',
            'site_disable_ip_history' => 'Disable IP history',
            'zip_downloader' => 'Download multiple torrents at once',
            'site_debug' => 'Developer access',
            'site_analysis' => 'Error analysis',
            'site_database_specifics' => 'Can view database specifics',
            'site_proxy_images' => 'Image proxy & anti-canary',
            'site_search_many' => 'Can go past low limit of search results',
            'site_user_stats' => 'Can view own user stat graphs',
            'site_unlimit_ajax' => 'Can bypass ajax api limits',
            'site_archive_ajax' => 'Can view archive related ajax endpoints',
            'users_edit_usernames' => 'Can edit usernames',
            'users_edit_ratio' => 'Can edit anyone\'s upload/download amounts',
            'users_edit_own_ratio' => 'Can edit own upload/download amounts',
            'users_edit_titles' => 'Can edit titles',
            'users_edit_avatars' => 'Can edit avatars',
            'users_edit_watch_hours' => 'Can edit contrib watch hours',
            'users_edit_reset_keys' => 'Can reset passkey/authkey',
            'users_edit_profiles' => 'Can edit anyone\'s profile',
            'users_view_friends' => 'Can view anyone\'s friends',
            'users_reset_own_keys' => 'Can reset own passkey/authkey',
            'users_edit_password' => 'Can change passwords',
            'users_promote_below' => 'Can promote users to below current level',
            'users_promote_to' => 'Can promote users up to current level',
            'users_give_donor' => 'Can give donor access',
            'users_warn' => 'Can warn users',
            'users_disable_users' => 'Can disable users',
            'users_disable_posts' => 'Can disable users\' posting privileges',
            'users_disable_any' => 'Can disable any users\' rights',
            'users_delete_users' => 'Can delete users',
            'users_view_seedleech' => 'Can view what a user is seeding or leeching',
            'users_view_uploaded' => 'Can view a user\'s uploads, regardless of privacy level',
            'users_view_keys' => 'Can view passkeys',
            'users_view_ips' => 'Can view IP addresses',
            'users_view_email' => 'Can view email addresses',
            'users_override_paranoia' => 'Can override paranoia',
            'users_logout' => 'Can log users out (old?)',
            'users_make_invisible' => 'Can make users invisible',
            'users_mod' => 'Basic moderator tools',
            'torrents_edit' => 'Can edit any torrent',
            'torrents_delete' => 'Can delete torrents',
            'torrents_delete_fast' => 'Can delete more than 3 torrents at a time',
            'torrents_freeleech' => 'Can make torrents freeleech',
            'torrents_search_fast' => 'Rapid search (for scripts)',
            'torrents_hide_dnu' => 'Hide the Do Not Upload list by default',
            'admin_manage_news' => 'Can manage site news',
            'admin_manage_blog' => 'Can manage the site blog',
            'admin_manage_contest' => 'Can manage contests',
            'admin_manage_polls' => 'Can manage polls',
            'admin_manage_forums' => 'Can manage forums (add/edit/delete)',
            'admin_manage_fls' => 'Can manage First Line Support (FLS) crew',
            'admin_manage_user_fls' => 'Can manage user FL tokens',
            'admin_manage_applicants' => 'Can manage job roles and user applications',
            'admin_manage_referrals' => 'Can manage referrals',
            'admin_view_notifications' => 'Can view notifications sandbox',
            'admin_view_payments' => 'Can view payments',
            'admin_manage_payments' => 'Can manage payments',
            'admin_manage_navigation' => 'Can manage navigation links',
            'admin_view_referrals' => 'Can view referred users',
            'admin_bp_history' => 'Can view bonus points spent by other users',
            'admin_fl_history' => 'Can view freeleech tokens spent by other users',
            'admin_reports' => 'Can access reports system',
            'admin_advanced_user_search' => 'Can access advanced user search',
            'admin_create_users' => 'Can create users through an administrative form',
            'admin_donor_log' => 'Can view the donor log',
            'admin_manage_stylesheets' => 'Can manage stylesheets',
            'admin_manage_ipbans' => 'Can manage IP bans',
            'admin_dnu' => 'Can manage do not upload list',
            'admin_clear_cache' => 'Can clear cached',
            'admin_global_notification' => 'Can send global notifications and direct messages',
            'admin_whitelist' => 'Can manage the list of allowed clients',
            'admin_manage_permissions' => 'Can edit permission classes/user permissions',
            'admin_recovery' => 'Can manage account recovery',
            'admin_schedule' => 'Can run the site schedule',
            'admin_site_debug' => 'Can access sensitive debug information',
            'admin_login_watch' => 'Can manage login watch',
            'admin_manage_wiki' => 'Can manage wiki access',
            'admin_update_geoip' => 'Can update geoIP data',
            'admin_staffpm_stats' => 'Can view Staff PM stats',
            'admin_periodic_task_manage' => 'Can manage periodic tasks',
            'admin_periodic_task_view' => 'Can view periodic task logs',
            'admin_rate_limit_manage' => 'Can manage rate limiting',
            'admin_rate_limit_view' => 'Can view rate limiting',
            'site_collages_recover' => 'Can recover \'deleted\' collages',
            'torrents_add_artist' => 'Can add artists to any group',
            'edit_unknowns' => 'Can edit unknown release information',
            'forums_polls_create' => 'Can create polls in the forums',
            'forums_polls_moderate' => 'Can feature and close polls',
            'torrents_edit_vanityhouse' => 'Can mark groups as part of Vanity House',
            'artist_edit_vanityhouse' => 'Can mark artists as part of Vanity House',
            'site_tag_aliases_read' => 'Can view the list of tag aliases',
        ];
    }

    /**
     * Check to see if a user has the permission to perform an action
     * This is called by check_perms in util.php, for convenience.
     *
     * @param string PermissionName
     * @param int $MinClass Return false if the user's class level is below this.
     *
     * @return bool
     */
    public static function check_perms($PermissionName, $MinClass = 0) {
        $Override = self::has_override(G::$LoggedUser['EffectiveClass']);
        return ($PermissionName === null ||
            (isset(G::$LoggedUser['Permissions'][$PermissionName]) && G::$LoggedUser['Permissions'][$PermissionName]))
            && (G::$LoggedUser['Class'] >= $MinClass
                || G::$LoggedUser['EffectiveClass'] >= $MinClass
                || $Override);
    }

    /**
     * Gets the permissions associated with a certain permissionid
     *
     * @param int $PermissionID the kind of permissions to fetch
     * @return array permissions
     */
    public static function get_permissions($PermissionID) {
        $Permission = G::$Cache->get_value("perm_$PermissionID");
        if (empty($Permission)) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->prepared_query("
                SELECT Level AS Class, `Values` AS Permissions, Secondary, PermittedForums
                FROM permissions
                WHERE ID = ?
                ", $PermissionID
            );
            $Permission = G::$DB->next_record(MYSQLI_ASSOC, ['Permissions']);
            G::$DB->set_query_id($QueryID);
            $Permission['Permissions'] = unserialize($Permission['Permissions']) ?: [];
            G::$Cache->cache_value("perm_$PermissionID", $Permission, 2592000);
        }
        return $Permission;
    }

    /**
     * Get a user's permissions.
     *
     * @param $UserID
     * @param array|false $CustomPermissions
     *    Pass in the user's custom permissions if you already have them.
     *    Leave false if you don't have their permissions. The function will fetch them.
     * @return array Mapping of PermissionName=>bool/int
     */
    public static function get_permissions_for_user($UserID, $CustomPermissions = false) {
        $UserInfo = Users::user_info($UserID);

        // Fetch custom permissions if they weren't passed in.
        if ($CustomPermissions === false) {
            $QueryID = G::$DB->get_query_id();
            $CustomPermissions = G::$DB->scalar("
                SELECT CustomPermissions FROM users_main WHERE ID = ?
                ", $UserID
            );
            G::$DB->set_query_id($QueryID);
        }

        if (!empty($CustomPermissions) && !is_array($CustomPermissions)) {
            $CustomPermissions = unserialize($CustomPermissions);
        }

        $Permissions = self::get_permissions($UserInfo['PermissionID']);

        // Manage 'special' inherited permissions
        $BonusPerms = [];
        foreach ($UserInfo['ExtraClasses'] as $PermID => $Value) {
            $ClassPerms = self::get_permissions($PermID);
            $BonusPerms = array_merge($BonusPerms, $ClassPerms['Permissions']);
        }

        if (empty($CustomPermissions)) {
            $CustomPermissions = [];
        }

        // Combine the permissions
        return array_merge(
            $Permissions['Permissions'],
            $BonusPerms,
            $CustomPermissions
        );
    }

    public static function has_permission($UserID, $privilege) {
        $Permissions = self::get_permissions_for_user($UserID);
        return isset($Permissions[$privilege]) && $Permissions[$privilege];
    }

    public static function has_override($Level) {
        static $max;
        if (is_null($max)) {
            $max = G::$DB->scalar('SELECT max(Level) FROM permissions');
        }
        return $Level >= $max;
    }
}
