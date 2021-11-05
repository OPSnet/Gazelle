<?php

namespace Gazelle\Manager;

class Privilege extends \Gazelle\Base {
    protected const ID_KEY = 'zz_prv_%d';
    protected const CACHE_KEY = 'privilege_list';

    protected array $info = [];

    public function findById(int $privilegeId): ?\Gazelle\Privilege {
        $key = sprintf(self::ID_KEY, $privilegeId);
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT ID FROM permissions WHERE ID = ?
                ", $privilegeId
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Privilege($id) : null;
    }

    public function findByLevel(int $level): ?\Gazelle\Privilege {
        $id = $this->db->scalar("
            SELECT ID FROM permissions WHERE Level = ?
            ", $level
        );
        return $id ? new \Gazelle\Privilege($id) : null;
    }

    public function flush() {
        $this->info = [];
        return $this;
    }

    public function create(string $name, int $level, bool $secondary, string $forums, array $values, mixed $staffGroup, string $badge, bool $displayStaff): \Gazelle\Privilege {
        $this->db->prepared_query('
            INSERT INTO permissions
                   (Name, Level, Secondary, PermittedForums, `Values`, StaffGroup, badge, DisplayStaff)
            VALUES (?,     ?,    ?,         ?,                ?,       ?,            ?,          ?)
            ', $name, $level, $secondary, $forums, serialize($values), $staffGroup, $badge, $displayStaff ? '1' : '0'
        );
        return new \Gazelle\Privilege($this->db->inserted_id());
    }

    protected function info() {
        if (empty($this->info)) {
            $info = $this->cache->get_value(self::CACHE_KEY);
            $info = false;
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

                $this->db->prepared_query("
                    SELECT ID
                    FROM permissions
                    ORDER BY Secondary DESC, Level, Name
                ");
                $classList = $this->db->to_array('ID', MYSQLI_ASSOC);

                // decorate the privileges with those user classes that have benn granted access
                foreach ($classList as $c) {
                    $perm = \Permissions::get_permissions($c['ID'])['Permissions'];
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
                        $privilege[$p]['can'][] = $c['ID'];
                    }
                }
                $this->info = [
                    'privilege' => $privilege,
                ];
                $this->cache->cache_value(self::CACHE_KEY, $this->info, 0);
            }
        }
        return $this->info;
    }

    /**
     * The list of defined privileges. The `can` field
     * in the returned array acts as a sparse matrix.
     *
     * @return array
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
            'site_edit_requests' => 'Can edit the metdata of a request',
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
            'admin_add_log' => 'Can add log files to any upload',
            'admin_manage_news' => 'Can manage site news',
            'admin_manage_blog' => 'Can manage the site blog',
            'admin_manage_contest' => 'Can manage contests',
            'admin_manage_polls' => 'Can manage polls',
            'admin_manage_forums' => 'Can manage forums (add/edit/delete)',
            'admin_manage_fls' => 'Can manage First Line Support (FLS) crew',
            'admin_manage_invite_source' => 'Can manage invite sources',
            'admin_manage_user_fls' => 'Can manage user FL tokens',
            'admin_manage_applicants' => 'Can manage job roles and user applications',
            'admin_manage_referrals' => 'Can manage referrals',
            'admin_view_notifications' => 'Can view notifications sandbox',
            'admin_view_payments' => 'Can view payments',
            'admin_manage_payments' => 'Can manage payments',
            'admin_manage_navigation' => 'Can manage navigation links',
            'admin_view_referrals' => 'Can view referred users',
            'admin_bp_history' => 'Can view bonus points spent by other users',
            'admin_freeleech' => 'Set torrents and collages freeleech',
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
}
