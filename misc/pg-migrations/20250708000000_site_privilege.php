<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SitePrivilege extends AbstractMigration {
    public function userclassPrivilege(string $name, string $privilege): void {
        $this->query("
            insert into privilege (id_privilege_group, seq, name, description)
            select pg.id_privilege_group, n.seq, n.name, n.description
            from (values $privilege) as n (seq, name, description)
            cross join privilege_group pg where pg.name = '$name'
        ");
    }

    /**
     * @param array<string> $privilege
     */
    public function userclassHasPrivilege(string $name, array $privilege): void {
        $list = implode(',', array_map(fn ($p) => "('$p')", $privilege));
        $this->query("
            insert into userclass_has_privilege (id_privilege, id_userclass)
            select p.id_privilege, uc.id_userclass
            from privilege p
            cross join userclass uc where uc.name = '$name'
                and  p.name in ($list)
        ");
    }

    public function up(): void {
        // abandoned privileges
        // site_archive_ajax -- subsumed (loggy)
        // site_proxy_images -- everything is proxied for everyone all the time
        // users_edit_own_ratio -- meaningless (scheduler activites will rewrite changes)
        // users_edit_ratio -- meaningless (scheduler activites will rewrite changes)
        // users_reset_own_keys -- never used

        $this->query("
            create table privilege_group (
                id_privilege_group int not null primary key,
                name varchar(32) not null,
                created timestamptz(0) not null default current_timestamp,
                description varchar(80) not null
            )
        ");
        $this->query("
            create unique index pg_n_uidx on privilege_group (name)
        ");

        $this->query("
            create table privilege (
                id_privilege_group int not null references privilege_group,
                id_privilege int not null generated always as identity,
                seq int not null,
                created timestamptz(0) not null default current_timestamp,
                name varchar(32) not null,
                description varchar(80) not null,
                primary key (id_privilege_group, id_privilege)
            )
        ");
        $this->query("
            create unique index p_n_uidx on privilege (name)
        ");
        $this->query("
            create unique index p_pg_s_uidx on privilege (id_privilege_group, seq)
        ");
        // p_p_uidx required for foreign key from userclass_has_privilege
        $this->query("
            create unique index p_p_uidx on privilege (id_privilege)
        ");

        $this->query("
            create table userclass_has_privilege (
                id_privilege int not null references privilege (id_privilege),
                id_userclass int not null,
                created timestamptz(0) not null default current_timestamp,
                primary key (id_privilege, id_userclass)
            )
        ");

        $this->query("
            insert into privilege_group (id_privilege_group, name, description)
            values
                (100, 'collage',     'Collage privileges'),
                (120, 'forum',       'Forum privileges'),
                (140, 'request',     'Request privileges'),
                (160, 'torrent',     'Torrent privileges'),
                (200, 'tag',         'Tag privileges'),
                (300, 'perk',        'Perks granted or unlocked by userclass'),
                (320, 'search',      'Advanced search facilities'),
                (400, 'stats',       'Statistics facilities'),
                (500, 'recruit',     'Recruitment privileges'),
                (600, 'user-view',   'View internal user information'),
                (650, 'user-admin',  'Privileges for user administration'),
                (700, 'admin',       'General site administration'),
                (750, 'freeleech',   'Freeleech administration'),
                (900, 'development', 'Site development toolkit'),
                (950, 'finance',     'Finance toolkit')
        ");

        $this->userclassPrivilege(
            'collage', "
            (1000, 'collage-personal-create', 'Can buy/create personal collages'), -- site_collages_personal
            (1010, 'collage-general-create', 'Can create (non-personal) collages'), -- site_collages_create
            (1020, 'collage-edit', 'Can manage the entires in collages'), -- site_collages_manage
            (1030, 'collage-subscribe', 'Can subscribe to collages'), -- site_collages_subscribe
            (1040, 'collage-personal-rename', 'Can omit their username in personal collages'), -- site_collages_renamepersonal
            (1100, 'collage-remove', 'Can delete collages'), -- site_collages_delete
            (1110, 'collage-recover', 'Can recover personal collages') -- site_collages_recover
        ");

        $this->userclassPrivilege(
            'forum', "
            (1000, 'forum-access', 'Can access the forums'), -- NEW
            (1010, 'forum-can-post', 'Can see option to auto-subscribe to all forum threads'), -- site_forum_autosub
            (2000, 'thread-autosub', 'Can see option to auto-subscribe to all forum threads'), -- site_forum_autosub
            (2010, 'forum-poll-create', 'Can create polls'), -- forums_polls_create
            (2020, 'forum-poll-manage', 'Can manager polls'), -- forums_polls_moderate
            (2030, 'forum-moderator', 'Can moderate forum threads'), -- site_moderate_forums
            (2040, 'forum-post-history', 'Can moderate forum actions'), -- site_admin_forums
            (2050, 'forum-post-delete', 'Can hard delete forum posts'), -- site_forum_post_delete
            (2060, 'thread-transition-edit', 'Can edit forum transitions'), -- admin_manage_forums
            (2070, 'forum-block-post', 'Can ban users from commenting') -- users_disable_posts
        ");

        $this->userclassPrivilege(
            'request', "
            (1000, 'request-create', 'Can create requests'), -- site_submit_requests
            (1010, 'request-vote', 'Can add bounty to requests'), -- site_vote
            (1020, 'request-edit', 'Can edit descriptive fields of requests'), -- site_edit_requests
            (1100, 'request-full-edit', 'Can  edit all fields of requests'), -- site_moderate_requests
            (1200, 'request-bounty-edit', 'Can edit and remove bounty from requests') -- site_admin_requests
        ");

        $this->userclassPrivilege(
            'torrent', "
            (1000, 'torrent-leech', 'Can leech torrents'), -- site_leech
            (1010, 'torrent-vote', 'Can upvote/downvote torrents'), -- site_album_votes
            (1100, 'torrent-artist-add', 'Can add artists to any torrent'), -- torrents_add_artist
            (1120, 'torrent-edit', 'Can edit torrent metadata'), -- torrents_edit
            (1130, 'lineage-edit', 'Can edit the lineage of Vinyl uploads'), -- site_edit_lineage
            (1200, 'torrent-riplog-add', 'Can add rip logs to any torrent'), -- admin_add_log
            (1210, 'unknown-edition-edit', 'Can edit unknown editions of torrent groups'), -- edit_unknowns
            (1300, 'showcase-artist', 'Can showcase any artist'), -- artist_edit_vanityhouse
            (1310, 'showcase-torrent', 'Can showcase any torrent'), -- torrents_edit_vanityhouse
            (2000, 'torrent-delete', 'Can delete torrents'), -- torrents_delete
            (2010, 'torrent-fast-delete', 'Can delete torrents beyond the standard rate'), -- torrents_delete_fast
            (5000, 'torrent-report-manage', 'Can manage torrent reports') -- admin_reports
        ");

        $this->userclassPrivilege(
            'perk', "
            (1000, 'acquire-bp', 'Can acquire bonus points from seeding'), -- NEW
            (1010, 'access-irc', 'Can access private IRC channels'), -- NEW
            (1020, 'set-avatar', 'Can set their own avatar'), -- NEW
            (1030, 'send-pm', 'Can send private messages'), -- NEW
            (1040, 'bookmark-create', 'Can create bookmarks'), -- site_make_bookmarks
            (2000, 'torrent-notify', 'Can create notifications of torrent uploads'), -- site_torrents_notify
            (2010, 'top10-view', 'Can see the upload Top10'), -- site_top10
            (2020, 'top10-advanced-view', 'Can see the extended Top10 pages'), -- site_advanced_top10
            (2030, 'hide-dnu', 'Can hide the Do Not Upload list by default'), -- torrents_hide_dnu
            (2040, 'zip-collector', 'Can use the torrent collector facility'), -- zip_downloader
            (2100, 'bypass-api-limit', 'Can exceed API rate limits'), -- site_unlimit_ajax
            (2110, 'edit-wiki', 'Can edit any writable article'), -- site_edit_wiki
            (2120, 'edit-viewable-wiki', 'Can edit any viewable article'), -- site_edit_wiki
            (3000, 'no-ip-history', 'Will not have ip history recorded'), -- site_disable_ip_history
            (3010, 'tor-access', 'Can access the site over the Tor network') -- can_use_tor
        ");

        $this->userclassPrivilege(
            'recruit', "
            (1000, 'invite-create', 'Can issue invites'), -- NEW
            (1010, 'invite-always', 'Can issue invites beyond user cap'), -- site_can_invite_always
            (1020, 'invite-unlimited', 'Has infinite invites'), -- site_send_unlimited_invites
            (1030, 'invite-add-note', 'Can add system note to invitations'), -- users_invite_notes
            (1040, 'invite-view', 'Can see inviter of users'), -- users_view_invites
            (1050, 'invite-revoke', 'Can revoke pending invitations'), -- users_edit_invites
            (1100, 'registration-flow', 'Can view the site registration flow'), -- site_view_flow
            (1110, 'view-linked-user', 'Can view linked users'), -- users_linked_users
            (1120, 'recovery', 'Can access the user recovery facility'), -- admin_recovery
            (1200, 'referral-view', 'Can view referrals'), -- admin_view_referrals
            (1210, 'referral-edit', 'Can edit referrals'), -- admin_manage_referrals
            (1300, 'applicant-manage', 'Can manage applicant roles'), -- admin_manage_applicants
            (1310, 'invite-source-edit', 'Can acces the invite source configuration') -- admin_manage_invite_source
        ");

        $this->userclassPrivilege(
            'search', "
            (1000, 'search-torrent-advanced', 'Can see the advanced torrent search form'), -- site_advanced_search
            (1010, 'search-many', 'Can search beyond the maximum permitted results'), -- site_search_many
            (2000, 'search-full-log', 'Can view the entire site log'), -- site_view_full_log
            (3000, 'search-user-advanced', 'Can access the advanced user search toolkit') -- admin_advanced_user_search
        ");

        $this->userclassPrivilege(
            'stats', "
            (1000, 'staffpm-stats', 'Can view staff PM statistics'), -- admin_staffpm_stats
            (1100, 'view-user-stats', 'Can view general user statistics') -- site_user_stats
        ");

        $this->userclassPrivilege(
            'tag', "
            (1000, 'tag-create', 'Can add tags to torrents and requests'), -- NEW
            (2000, 'tag-alias-view', 'Can view tag aliases'), -- site_tag_aliases_read
            (2010, 'tag-delete', 'Can delete tags') -- site_delete_tag
        ");

        $this->userclassPrivilege(
            'upload', "
            (1000, 'torrent-upload', 'Can upload torrents'), -- site_upload
            (2000, 'download-limit-edit', 'Can edit the download factor'), -- admin_rate_limit_manage
            (2010, 'download-limit-view', 'Can view user download factors') -- admin_rate_limit_view
        ");

        $this->userclassPrivilege(
            'admin', "
            (1000, 'toolbox-view', 'Can view the staff toolbox'), -- users_mod
            (1010, 'global-notification', 'Can send global notifications'), -- admin_global_notification
            (1020, 'news-edit', 'Can edit news articles'), -- admin_manage_news
            (1030, 'blog-edit', 'Can edit blog articles'), -- admin_manage_blog
            (1040, 'upload-notifier-view', 'Can view all notifications of an upload'), -- admin_view_notifications
            (1050, 'donor-edit', 'Can (un)mark a user as a donor'), -- users_give_donor
            (1100, 'contest-manage', 'Can manage site contests'), -- admin_manage_contest
            (1110, 'artist-remove', 'Can remove unused artists'), -- site_delete_artist
            (1120, 'wiki-remove', 'Can remove wiki articles'), -- admin_manage_wiki
            (1200, 'stylesheet-edit', 'Can manage stylesheets'), -- admin_manage_stylesheets
            (1220, 'dnu-edit', 'Can manage the DNU list'), -- admin_dnu
            (1230, 'bt-client-edit', 'Can edit authorized Bittorrent clients'), -- admin_whitelist
            (1240, 'ip-ban-manage', 'Can manage blacklisted IP addresses'), -- admin_manage_ipbans
            (1300, 'tracker-view', 'Can view tracker statistics'), -- admin_tracker
            (1310, 'tracker-edit', 'Can edit tracker configuration'), -- admin_tracker
            (9999, 'privilege-manage', 'Can manage global site privileges (dangerous!)') -- admin_manage_permissions
        ");

        $this->userclassPrivilege(
            'development', "
            (1000, 'schedule-view', 'Can view scheduled task statistics'), -- admin_schedule
            (1010, 'schedule-task-edit', 'Can edit scheduled task details'), -- admin_periodic_task_manage
            (1020, 'schedule-task-view', 'Can view the history of a scheduled task'), -- admin_periodic_task_view
            (2000, 'error-log-view', 'Can view the site error log'), -- site_analysis
            (2010, 'cache-edit', 'Can view cache storage'), -- admin_clear_cache
            (2020, 'db-inspector', 'Can view and query database tables'), -- site_database_specifics
            (2030, 'site-debug', 'Can acccess site debug tools'), -- site_debug
            (2040, 'site-admin-debug', 'Can view site information') -- admin_site_debug
        ");

        $this->userclassPrivilege(
            'finance', "
            (1000, 'donor-log-view', 'Can view the donations log'), -- admin_donor_log
            (1010, 'payment-view', 'Can view payment schedules'), -- admin_view_payments
            (1020, 'payment-edit', 'Can edit payment schedules') -- admin_manage_payments
        ");

        $this->userclassPrivilege(
            'freeleech', "
            (1000, 'freeleech-token-edit', 'Can send freeleech tokens'), -- admin_freeleech
            (1010, 'feature-album', 'Can feature albums (AoTM)'), -- admin_freeleech
            (1020, 'freeleech-user-edit', 'Can adjust user freeleech token amount'), -- admin_manage_user_fls
            (1030, 'freeleech-edit', 'Set torrents neutral/freeleech'), -- torrents_freeleech
            (1040, 'freeleech-history', 'View user freeleech token usage') -- admin_fl_history
        ");

        $this->userclassPrivilege(
            'user-admin', "
            (1000, 'audit-view', 'Can view the user audit trail'), -- admin_audit_view
            (1010, 'audit-edit', 'Can edit the user audit trail'), -- admin_audit_edit
            (1020, 'fls-title', 'Can set FLS titles'), -- admin_manage_fls
            (1030, 'login-watcher', 'Can view login attempts'), -- admin_login_watch
            (1040, 'auto-report', 'Can view auto reports'), -- users_auto_reports
            (2010, 'profile-edit', 'Can edit user profiles'), -- users_edit_profiles
            (2020, 'username-edit', 'Can edit usernames'), -- users_edit_usernames
            (2030, 'avatar-edit', 'Can edit user avatars'), -- users_edit_avatars
            (2040, 'user-title-edit', 'Can edit custom titles'), -- users_edit_titles
            (2050, 'announce-key-edit', 'Can edit user announce keys'), -- users_edit_reset_keys
            (2060, 'password-edit', 'Can edit user passwords'), -- users_edit_password
            (3000, 'warn', 'Can warn users'), -- users_warn
            (3010, 'user-action-disable', 'Can remove user actions (bp, invites, posting, ...)'), -- users_disable_any
            (3020, 'user-logout', 'Can logout users'), -- users_logout
            (3030, 'user-site-disable', 'Can ban users'), -- users_disable_users
            (4000, 'user-create', 'Can create users'), -- admin_create_users
            (4010, 'user-menu-edit', 'Can define user menu items'), -- admin_manage_navigation
            (4020, 'make-peer-invisible', 'Can make users invisible in swarms'), -- users_make_invisible
            (5000, 'user-remove', 'Can hard-delete users') -- users_delete_users
        ");

        $this->userclassPrivilege(
            'user-view', "
            (1000, 'paranoia-override', 'Can override user paranoia'), -- users_override_paranoia
            (1010, 'view-last-seen', 'Can view when users were last seen'), -- view_last_seen
            (1020, 'view-snatch', 'Can view user snatch lists'), -- site_view_torrent_snatchlist
            (1030, 'view-seed-leech', 'Can view user seeding and leeching lists'), -- users_view_seedleech
            (1040, 'view-uploaded', 'Can view user upload lists'), -- users_view_uploaded
            (1050, 'view-friend', 'Can view friend lists'), -- users_view_friends
            (1060, 'view-bp-history', 'Can view BP purchase history'), -- admin_bp_history
            (1070, 'view-announce-keys', 'Can view announce keys'), -- users_view_keys
            (1080, 'view-ip', 'Can view user IP addresses'), -- users_view_ips
            (1090, 'view-email', 'Can view user email addresses') -- users_view_email
        ");

        $this->query("
            create table userclass (
                id_userclass int not null primary key,
                name varchar(32) not null,
                is_staff bool not null
            )
        ");

        $this->userclassHasPrivilege(
            'User',
            [
                'access-irc',
                'acquire-bp',
                'collage-personal-create',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'top10-view',
                'torrent-artist-add',
                'torrent-upload',
                'torrent-vote',
            ]
        );

        $this->userclassHasPrivilege(
            'Member',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-personal-create',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'top10-view',
                'torrent-artist-add',
                'torrent-leech',
                'torrent-upload',
                'torrent-vote',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Power User',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-general-create',
                'collage-personal-create',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'forum-poll-create',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'top10-view',
                'torrent-artist-add',
                'torrent-leech',
                'torrent-notify',
                'torrent-upload',
                'torrent-vote',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Elite',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-general-create',
                'collage-personal-create',
                'collage-personal-rename',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'forum-poll-create',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'tag-delete',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-edit',
                'torrent-leech',
                'torrent-notify',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Torrent Master',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-general-create',
                'collage-personal-rename',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'forum-poll-create',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'tag-delete',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-edit',
                'torrent-leech',
                'torrent-notify',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Power TM',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-general-create',
                'collage-personal-rename',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'forum-poll-create',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'tag-delete',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-edit',
                'torrent-leech',
                'torrent-notify',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Elite TM',
            [
                'access-irc',
                'acquire-bp',
                'bookmark-create',
                'collage-edit',
                'collage-general-create',
                'collage-personal-create',
                'collage-personal-rename',
                'collage-subscribe',
                'edit-wiki',
                'forum-access',
                'forum-can-post',
                'forum-poll-create',
                'invite-unlimited',
                'request-create',
                'request-vote',
                'search-torrent-advanced',
                'send-pm',
                'set-avatar',
                'tag-create',
                'tag-delete',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-edit',
                'torrent-leech',
                'torrent-notify',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'zip-collector',
            ],
        );

        $this->userclassHasPrivilege(
            'Legend',
            [
                'access-irc',
                'acquire-bp',
                'collage-personal-create',
                'forum-access',
                'forum-can-post',
                'send-pm',
                'set-avatar',
                'tag-create',
            ],
        );

        $this->userclassHasPrivilege(
            'Moderator',
            [
                'access-irc',
                'acquire-bp',
                'announce-key-edit',
                'artist-remove',
                'avatar-edit',
                'bookmark-create',
                'bt-client-edit',
                'cache-edit',
                'collage-edit',
                'collage-general-create',
                'collage-personal-create',
                'collage-personal-rename',
                'collage-remove',
                'collage-subscribe',
                'edit-wiki',
                'fls-title',
                'forum-access',
                'forum-block-post',
                'forum-can-post',
                'forum-moderator',
                'forum-poll-create',
                'forum-post-history',
                'freeleech-edit',
                'invite-add-note',
                'invite-revoke',
                'invite-unlimited',
                'invite-view',
                'no-ip-history',
                'paranoia-override',
                'request-create',
                'request-full-edit',
                'request-vote',
                'search-many',
                'search-torrent-advanced',
                'search-user-advanced',
                'send-pm',
                'set-avatar',
                'tag-alias-view',
                'tag-create',
                'tag-delete',
                'toolbox-view',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-delete',
                'torrent-edit',
                'torrent-fast-delete',
                'torrent-leech',
                'torrent-notify',
                'torrent-report-manage',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'user-action-disable',
                'user-logout',
                'user-site-disable',
                'user-title-edit',
                'view-announce-keys',
                'view-email',
                'view-friend',
                'view-ip',
                'view-seed-leech',
                'view-snatchlist',
                'view-uploaded',
                'warn',
                'zip-collector',
            ]
        );

        $this->userclassHasPrivilege(
            'Administrator',
            [
                'access-irc',
                'acquire-bp',
                'announce-key-edit',
                'artist-remove',
                'avatar-edit',
                'blog-edit',
                'bookmark-create',
                'bt-client-edit',
                'cache-edit',
                'collage-edit',
                'collage-general-create',
                'collage-personal-create',
                'collage-personal-rename',
                'collage-recover',
                'collage-remove',
                'collage-subscribe',
                'dnu-edit',
                'edit-wiki',
                'fls-title',
                'forum-access',
                'forum-block-post',
                'forum-can-post',
                'forum-moderator',
                'forum-poll-create',
                'forum-poll-manage',
                'forum-post-history',
                'freeleech-edit',
                'invite-add-note',
                'invite-always',
                'invite-revoke',
                'invite-unlimited',
                'invite-view',
                'ip-ban-manage',
                'no-ip-history',
                'paranoia-override',
                'password-edit',
                'profile-edit',
                'registration-flow',
                'request-create',
                'request-full-edit',
                'request-vote',
                'search-full-log',
                'search-many',
                'search-torrent-advanced',
                'search-user-advanced',
                'send-pm',
                'set-avatar',
                'showcase-artist',
                'showcase-torrent',
                'tag-alias-view',
                'tag-create',
                'tag-delete',
                'toolbox-view',
                'top10-advanced-view',
                'top10-view',
                'torrent-artist-add',
                'torrent-delete',
                'torrent-edit',
                'torrent-fast-delete',
                'torrent-leech',
                'torrent-notify',
                'torrent-report-manage',
                'torrent-upload',
                'torrent-vote',
                'unknown-edition-edit',
                'user-action-disable',
                'user-logout',
                'user-remove',
                'user-roadkill',
                'user-site-disable',
                'user-title-edit',
                'view-announce-keys',
                'view-email',
                'view-friend',
                'view-ip',
                'view-seed-leech',
                'view-snatchlist',
                'view-uploaded',
                'warn',
                'wiki-remove',
                'zip-collector',
            ],
        );

        $this->query("
            insert into userclass_has_privilege (id_privilege, id_userclass)
            select p.id_privilege, uc.id_userclass
            from privilege p
            cross join userclass uc where uc.name = 'Sysop'
        ");
    }

    public function down(): void {
        $this->table('userclass_has_privilege')->drop()->save();
        $this->table('userclass')->drop()->save();
        $this->table('privilege')->drop()->save();
        $this->table('privilege_group')->drop()->save();
    }
}
