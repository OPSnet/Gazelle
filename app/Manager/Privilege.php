<?php

namespace Gazelle\Manager;

class Privilege extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_prv_%d';
    protected const CACHE_KEY = 'privilege_list';

    protected array $info = [];

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_KEY);
        $this->info = [];
        return $this;
    }

    public function create(string $name, int $level, int $secondary, string $forums, array $values, int|null $staffGroupId, string $badge, bool $displayStaff): \Gazelle\Privilege {
        self::$db->prepared_query('
            INSERT INTO permissions
                   (Name, Level, Secondary, PermittedForums, `Values`, StaffGroup, badge, DisplayStaff)
            VALUES (?,     ?,    ?,         ?,                ?,       ?,            ?,          ?)
            ', $name, $level, $secondary, $forums, serialize($values), $staffGroupId, $badge, $displayStaff ? '1' : '0'
        );
        self::$cache->delete_multi(['user_class', 'staff_class']);
        return new \Gazelle\Privilege(self::$db->inserted_id());
    }

    public function findById(int $privilegeId): ?\Gazelle\Privilege {
        $key = sprintf(self::ID_KEY, $privilegeId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = (int)self::$db->scalar("
                SELECT ID FROM permissions WHERE ID = ?
                ", $privilegeId
            );
            if ($id) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Privilege($id) : null;
    }

    public function findByLevel(int $level): ?\Gazelle\Privilege {
        $id = (int)self::$db->scalar("
            SELECT ID FROM permissions WHERE Level = ?
            ", $level
        );
        return $id ? new \Gazelle\Privilege($id) : null;
    }

    protected function info(): array {
        if (empty($this->info)) {
            $info = self::$cache->get_value(self::CACHE_KEY);
            if ($info !== false) {
                $this->info = $info;
            } else {
                $privilege = [];
                $plist = self::privilegeList();
                foreach ($plist as $name => $description) {
                    $privilege[$name] = [
                        'can'         => [],
                        'description' => $description,
                        'name'        => $name,
                        'orphan'      => 0
                    ];
                }

                self::$db->prepared_query("
                    SELECT ID, `Values` AS Permissions
                    FROM permissions
                    ORDER BY Secondary DESC, Level, Name
                ");
                $permission = self::$db->to_pair('ID', 'Permissions', false);

                // decorate the privileges with those user classes that have benn granted access
                foreach ($permission as $id => $perm) {
                    $perm = unserialize($perm);
                    foreach (array_keys($perm) as $p) {
                        if (!isset($privilege[$p])) {
                            // orphan permissions in the db that no longer do anything
                            $privilege[$p] = [
                                'can'         => [],
                                'description' => $p,
                                'name'        => $p,
                                'orphan'      => 1
                            ];
                        }
                        $privilege[$p]['can'][] = $id;
                    }
                }
                $this->info = [
                    'privilege' => $privilege,
                ];
                self::$cache->cache_value(self::CACHE_KEY, $this->info, 7200);
            }
        }
        return $this->info;
    }

    /**
     * Statistics on how many people are in the different user classes.
     */
    public function usageList(): array {
        self::$db->prepared_query("
            SELECT p.ID     AS id,
                p.Name      AS name,
                p.Level     AS level,
                p.Secondary AS is_secondary,
                sg.Name     AS staff_group,
                count(um.ID) + count(DISTINCT ul.UserID) AS total
            FROM permissions AS p
            LEFT JOIN staff_groups AS sg ON (sg.ID = p.StaffGroup)
            LEFT JOIN users_main   AS um ON (um.PermissionID = p.ID)
            LEFT JOIN users_levels AS ul ON (ul.PermissionID = p.ID)
            GROUP BY p.ID
            ORDER BY p.Secondary ASC, p.Level ASC
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * The list of defined privileges. The `can` field
     * in the returned array acts as a sparse matrix.
     *
     *      - name (Short name of privilege)
     *      - description (Longer description of privilege)
     *      - orphan (Is this a privileges that no longer exists)
     *      - can (array of user class permission IDs that have this privilege)
     */
    public function privilege(): array {
        return $this->info()['privilege'];
    }

    public static function privilegeList(): array {
        return [
            'site_upload' => 'Upload torrent access',
            'site_submit_requests' => 'Create requests',
            'site_vote' => 'Vote on requests',
            'site_advanced_search' => 'Advanced search',
            'site_top10' => 'View Top 10',
            'site_advanced_top10' => 'View advanced Top 10',
            'site_album_votes' => 'Vote for favorite releases',
            'site_torrents_notify' => 'Access upload notifications',
            'site_collages_create' => 'Create collages',
            'site_collages_manage' => 'Edit collages',
            'site_collages_delete' => 'Delete Collages',
            'site_collages_subscribe' => 'Subscribe to collages',
            'site_collages_personal' => 'Create a personal collage',
            'site_collages_renamepersonal' => 'rename personal collages',
            'site_make_bookmarks' => 'Create bookmarks',
            'site_edit_wiki' => 'Edit the wiki',
            'users_view_invites' => 'View invitees of a user',
            'site_send_unlimited_invites' => 'Unlimited invites up to maximum user count',
            'site_can_invite_always' => 'Invite beyond maximum user count',
            'users_invite_notes' => 'Add a staff note when inviting someone',
            'users_edit_invites' => 'Edit invite numbers and cancel sent invites',
            'site_edit_lineage' => 'Edit the missing lineage flag',
            'site_edit_requests' => 'Edit the metdata of a request',
            'site_admin_requests' => 'Edit request bounties',
            'site_moderate_requests' => 'Moderate requests',
            'site_delete_artist' => 'Delete artists (needs site_moderate_requests + torrents_delete)',
            'site_moderate_forums' => 'Moderate forums',
            'site_forum_post_delete' => 'Hard delete forum posts',
            'site_forum_autosub' => 'Be subscribed automatically to new threads',
            'site_admin_forums' => 'Administrate forums',
            'site_view_flow' => 'View stats and data pools',
            'site_view_full_log' => 'View old log entries',
            'site_view_torrent_snatchlist' => 'View torrent snatch lists',
            'site_delete_tag' => 'Delete tags',
            'site_disable_ip_history' => 'Disable IP history',
            'zip_downloader' => 'Use the collector',
            'site_debug' => 'Developer access',
            'site_analysis' => 'Error analysis',
            'site_database_specifics' => 'View database specifics',
            'site_proxy_images' => 'Image proxy',
            'site_search_many' => 'Go past low limit of search results',
            'site_user_stats' => 'view other user stat graphs',
            'site_unlimit_ajax' => 'Bypass ajax api limits',
            'site_archive_ajax' => 'View archive-related ajax endpoints',
            'users_edit_usernames' => 'Edit usernames',
            'users_edit_ratio' => 'Edit upload/download amounts',
            'users_edit_own_ratio' => 'Edit own upload/download amounts',
            'users_edit_titles' => 'Edit user custom titles',
            'users_edit_avatars' => 'Edit avatars',
            'users_edit_reset_keys' => 'Reset user passkey/authkey',
            'users_edit_profiles' => 'Edit user profiles',
            'users_linked_users' => 'View and edit linked users',
            'users_view_friends' => 'View user friends',
            'users_reset_own_keys' => 'Reset own passkey/authkey',
            'users_edit_password' => 'Edit passwords',
            'users_promote_below' => 'Promote users to below own level',
            'users_promote_to' => 'Promote users up to own level',
            'users_give_donor' => 'Grant donor status',
            'users_warn' => 'Warn users',
            'users_disable_users' => 'Disable users',
            'users_disable_posts' => 'Disable posting privileges',
            'users_disable_any' => 'Disable any user privileges',
            'users_delete_users' => 'Hard delete users',
            'users_view_seedleech' => 'View user seeding/leeching',
            'users_view_uploaded' => 'View user uploads',
            'users_view_keys' => 'View passkeys',
            'users_view_ips' => 'View IP addresses',
            'users_view_email' => 'View email addresses',
            'view_last_seen' => 'View when user was last seen',
            'users_override_paranoia' => 'Override paranoia',
            'users_logout' => 'Log users out',
            'users_make_invisible' => 'Hide username in seeder lists',
            'users_mod' => 'Basic moderator tools',
            'torrents_delete' => 'Can delete torrents',
            'admin_freeleech' => 'Set torrents and collages freeleech',
            'torrents_edit' => 'Edit any torrent',
            'torrents_delete_fast' => 'Delete more than 3 torrents at a time',
            'torrents_freeleech' => 'Make torrents freeleech',
            'torrents_hide_dnu' => 'Hide the Do Not Upload list',
            'admin_add_log' => 'Add rip logs to any upload',
            'admin_manage_news' => 'Manage site news',
            'admin_manage_blog' => 'Manage the site blog',
            'admin_manage_contest' => 'Manage contests',
            'admin_manage_polls' => 'Manage front page polls',
            'admin_manage_forums' => 'Manage forums departments',
            'admin_manage_fls' => 'Manage First Line Support (FLS) crew',
            'admin_manage_invite_source' => 'Manage invite sources',
            'admin_manage_user_fls' => 'manage user FL tokens',
            'admin_manage_applicants' => 'Manage job roles and user applications',
            'admin_manage_referrals' => 'Manage referrals',
            'admin_view_notifications' => 'View notifications sandbox',
            'admin_view_payments' => 'View payments',
            'admin_manage_payments' => 'Edit payments',
            'admin_manage_navigation' => 'Manage navigation links',
            'admin_view_referrals' => 'View referred users',
            'admin_bp_history' => 'View bonus points spent by other users',
            'admin_fl_history' => 'View freeleech tokens spent by other users',
            'admin_reports' => 'Access reports system',
            'admin_advanced_user_search' => 'Advanced user search',
            'admin_create_users' => 'Create new user',
            'admin_donor_log' => 'View the donor log',
            'admin_manage_stylesheets' => 'Manage stylesheets',
            'admin_manage_ipbans' => 'Manage IP bans',
            'admin_dnu' => 'Manage the Do Not Upload list',
            'admin_clear_cache' => 'Invalidate an object cache',
            'admin_global_notification' => 'Send global notifications and direct messages',
            'admin_whitelist' => 'Manage authorized Bittorrent clients',
            'admin_manage_permissions' => 'Edit userclasses',
            'admin_recovery' => 'Manage account recovery',
            'admin_manage_wiki' => 'Manage wiki access',
            'admin_staffpm_stats' => 'View Staff PM stats',
            'admin_login_watch' => 'Manage login watch',
            'admin_site_debug' => 'Access debug information',
            'admin_tracker' => 'Manage tracker configuration variables',
            'admin_schedule' => 'Run the site schedule',
            'admin_periodic_task_manage' => 'Manage scheduler',
            'admin_periodic_task_view' => 'View scheduler logs',
            'admin_rate_limit_manage' => 'Manage rate limiting',
            'admin_rate_limit_view' => 'View rate limiting',
            'site_collages_recover' => 'Recover \'deleted\' collages',
            'torrents_add_artist' => 'Add artists to any group',
            'edit_unknowns' => 'Edit unknown release information',
            'forums_polls_create' => 'Create forum polls',
            'forums_polls_moderate' => 'Feature and close polls',
            'torrents_edit_vanityhouse' => 'Mark groups as Showcase',
            'artist_edit_vanityhouse' => 'Mark artists as Showcase',
            'site_tag_aliases_read' => 'View tag aliases',
            'can_use_tor' => 'Can access the site over Tor',
        ];
    }
}
