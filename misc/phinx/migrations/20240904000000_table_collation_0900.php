<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * During the migration from utf8 (utf8mb3) to utf8mb4, a number
 * of tables were adjusted in production but not in the development
 * repository. This adjusts all the outstanding issues with tables
 * having the wrong character set or collation.
 *
 * Afterwards, the following queries should return the empty set:
 *
select table_name
from information_schema.tables
where table_schema = 'gazelle'
    and table_collation != 'utf8mb4_0900_ai_ci';

select table_name, column_name, character_set_name
from information_schema.columns
where table_schema = 'gazelle'
    and character_set_name != 'utf8mb4';
 *
 * In general this is not a problem but it was possible to craft
 * ad-hoc queries which went pear-shaped when joining on columns
 * with different collations.
 */

function tableList(): array { /** @phpstan-ignore-line */
    return [
        'applicant_role_has_user' => 'utf8mb3_general_ci',
        'better_transcode_music' => 'utf8mb3_general_ci',
        'category' => 'utf8mb3_general_ci',
        'deleted_torrent_has_attr' => 'utf8mb3_general_ci',
        'do_not_upload' => 'utf8mb4_unicode_ci',
        'donations' => 'utf8mb4_unicode_ci',
        'donor_forum_usernames' => 'utf8mb4_unicode_ci',
        'donor_rewards' => 'utf8mb4_unicode_ci',
        'dupe_groups' => 'utf8mb4_unicode_ci',
        'email_blacklist' => 'utf8mb4_unicode_ci',
        'error_log' => 'utf8mb4_unicode_ci',
        'featured_albums' => 'utf8mb4_unicode_ci',
        'forums' => 'utf8mb4_unicode_ci',
        'forums_categories' => 'utf8mb4_unicode_ci',
        'forums_last_read_topics' => 'utf8mb3_general_ci',
        'forums_polls' => 'utf8mb4_unicode_ci',
        'forums_polls_votes' => 'utf8mb3_general_ci',
        'forums_posts' => 'utf8mb4_unicode_ci',
        'forums_topic_notes' => 'utf8mb4_unicode_ci',
        'forums_topics' => 'utf8mb4_unicode_ci',
        'forums_transitions' => 'utf8mb4_unicode_ci',
        'friends' => 'utf8mb4_unicode_ci',
        'group_log' => 'utf8mb4_unicode_ci',
        'invite_source' => 'utf8mb4_unicode_ci',
        'invite_source_pending' => 'utf8mb4_unicode_ci',
        'invite_tree' => 'utf8mb3_general_ci',
        'inviter_has_invite_source' => 'utf8mb3_general_ci',
        'invites' => 'utf8mb4_unicode_ci',
        'ip_bans' => 'utf8mb4_unicode_ci',
        'lastfm_users' => 'utf8mb4_unicode_ci',
        'locked_accounts' => 'utf8mb3_general_ci',
        'log' => 'utf8mb4_unicode_ci',
        'login_attempts' => 'utf8mb4_unicode_ci',
        'nav_items' => 'utf8mb4_unicode_ci',
        'news' => 'utf8mb4_unicode_ci',
        'payment_reminders' => 'utf8mb4_unicode_ci',
        'periodic_task' => 'utf8mb4_unicode_ci',
        'periodic_task_history' => 'utf8mb4_unicode_ci',
        'periodic_task_history_event' => 'utf8mb4_unicode_ci',
        'permission_rate_limit' => 'utf8mb3_general_ci',
        'permissions' => 'utf8mb4_unicode_ci',
        'phinxlog' => 'utf8mb4_unicode_ci',
        'pm_conversations' => 'utf8mb3_general_ci',
        'pm_conversations_users' => 'utf8mb4_unicode_ci',
        'pm_messages' => 'utf8mb3_general_ci',
        'push_notifications_usage' => 'utf8mb4_unicode_ci',
        'ratelimit_torrent' => 'utf8mb3_general_ci',
        'recovery_buffer' => 'utf8mb4_unicode_ci',
        'referral_accounts' => 'utf8mb4_unicode_ci',
        'referral_users' => 'utf8mb4_unicode_ci',
        'release_type' => 'utf8mb4_unicode_ci',
        'reports' => 'utf8mb4_unicode_ci',
        'reportsv2' => 'utf8mb4_unicode_ci',
        'requests' => 'utf8mb4_unicode_ci',
        'requests_artists' => 'utf8mb4_unicode_ci',
        'requests_tags' => 'utf8mb3_general_ci',
        'requests_votes' => 'utf8mb3_general_ci',
        'site_options' => 'utf8mb4_unicode_ci',
        'sphinx_a' => 'utf8mb4_unicode_ci',
        'sphinx_delta' => 'utf8mb4_unicode_ci',
        'sphinx_index_last_pos' => 'utf8mb4_unicode_ci',
        'sphinx_requests' => 'utf8mb4_unicode_ci',
        'sphinx_requests_delta' => 'utf8mb4_unicode_ci',
        'sphinx_t' => 'utf8mb4_unicode_ci',
        'sphinx_tg' => 'utf8mb4_unicode_ci',
        'staff_blog' => 'utf8mb4_unicode_ci',
        'staff_blog_visits' => 'utf8mb3_general_ci',
        'staff_groups' => 'utf8mb4_unicode_ci',
        'staff_pm_conversations' => 'utf8mb4_unicode_ci',
        'staff_pm_messages' => 'utf8mb3_general_ci',
        'staff_pm_responses' => 'utf8mb3_general_ci',
        'stylesheets' => 'utf8mb4_unicode_ci',
        'tag_aliases' => 'utf8mb4_unicode_ci',
        'tags' => 'utf8mb4_unicode_ci',
        'tgroup_summary' => 'utf8mb3_general_ci',
        'thread' => 'utf8mb3_general_ci',
        'thread_note' => 'utf8mb4_unicode_ci',
        'thread_type' => 'utf8mb4_unicode_ci',
        'top10_history' => 'utf8mb4_unicode_ci',
        'top10_history_torrents' => 'utf8mb4_unicode_ci',
        'torrent_attr' => 'utf8mb4_unicode_ci',
        'torrent_group_attr' => 'utf8mb4_unicode_ci',
        'torrent_group_has_attr' => 'utf8mb3_general_ci',
        'torrent_has_attr' => 'utf8mb3_general_ci',
        'torrent_report_configuration' => 'utf8mb3_general_ci',
        'torrent_report_configuration_log' => 'utf8mb3_general_ci',
        'torrent_unseeded' => 'utf8mb3_general_ci',
        'torrent_unseeded_claim' => 'utf8mb3_general_ci',
        'torrents' => 'utf8mb4_unicode_ci',
        'torrents_artists' => 'utf8mb4_unicode_ci',
        'torrents_group' => 'utf8mb4_unicode_ci',
        'torrents_leech_stats' => 'utf8mb3_general_ci',
        'torrents_logs' => 'utf8mb4_unicode_ci',
        'torrents_peerlists' => 'utf8mb3_general_ci',
        'torrents_peerlists_compare' => 'utf8mb3_general_ci',
        'torrents_tags' => 'utf8mb3_general_ci',
        'torrents_tags_votes' => 'utf8mb4_unicode_ci',
        'torrents_votes' => 'utf8mb3_general_ci',
        'user_attr' => 'utf8mb4_unicode_ci',
        'user_bonus' => 'utf8mb3_general_ci',
        'user_flt' => 'utf8mb3_general_ci',
        'user_has_attr' => 'utf8mb3_general_ci',
        'user_has_invite_source' => 'utf8mb3_general_ci',
        'user_has_ordinal' => 'utf8mb3_general_ci',
        'user_last_access' => 'utf8mb3_general_ci',
        'user_last_access_delta' => 'utf8mb3_general_ci',
        'user_ordinal' => 'utf8mb3_general_ci',
        'user_read_blog' => 'utf8mb3_general_ci',
        'user_read_forum' => 'utf8mb3_general_ci',
        'user_read_news' => 'utf8mb3_general_ci',
        'user_seedbox' => 'utf8mb4_unicode_ci',
        'user_summary' => 'utf8mb3_general_ci',
        'user_torrent_remove' => 'utf8mb3_general_ci',
        'users_collage_subs' => 'utf8mb3_general_ci',
        'users_comments_last_read' => 'utf8mb4_unicode_ci',
        'users_donor_ranks' => 'utf8mb3_general_ci',
        'users_downloads' => 'utf8mb3_general_ci',
        'users_dupes' => 'utf8mb3_general_ci',
        'users_enable_requests' => 'utf8mb4_unicode_ci',
        'users_freeleeches' => 'utf8mb3_general_ci',
        'users_geodistribution' => 'utf8mb4_unicode_ci',
        'users_history_emails' => 'utf8mb4_unicode_ci',
        'users_history_ips' => 'utf8mb4_unicode_ci',
        'users_history_passkeys' => 'utf8mb4_unicode_ci',
        'users_history_passwords' => 'utf8mb4_unicode_ci',
        'users_info' => 'utf8mb4_unicode_ci',
        'users_leech_stats' => 'utf8mb3_general_ci',
        'users_levels' => 'utf8mb3_general_ci',
        'users_main' => 'utf8mb4_unicode_ci',
        'users_notifications_settings' => 'utf8mb3_general_ci',
        'users_notify_filters' => 'utf8mb4_unicode_ci',
        'users_notify_quoted' => 'utf8mb4_unicode_ci',
        'users_notify_torrents' => 'utf8mb3_general_ci',
        'users_push_notifications' => 'utf8mb4_unicode_ci',
        'users_sessions' => 'utf8mb4_unicode_ci',
        'users_stats_daily' => 'utf8mb3_general_ci',
        'users_stats_monthly' => 'utf8mb3_general_ci',
        'users_stats_yearly' => 'utf8mb3_general_ci',
        'users_subscriptions' => 'utf8mb3_general_ci',
        'users_subscriptions_comments' => 'utf8mb4_unicode_ci',
        'users_torrent_history' => 'utf8mb4_unicode_ci',
        'users_votes' => 'utf8mb4_unicode_ci',
        'users_warnings_forums' => 'utf8mb4_unicode_ci',
        'wiki_aliases' => 'utf8mb4_unicode_ci',
        'wiki_articles' => 'utf8mb3_general_ci',
        'wiki_artists' => 'utf8mb4_unicode_ci',
        'wiki_revisions' => 'utf8mb3_general_ci',
        'wiki_torrents' => 'utf8mb4_unicode_ci',
        'xbt_client_whitelist' => 'utf8mb4_unicode_ci',
        'xbt_files_users' => 'utf8mb4_unicode_ci',
        'xbt_forex' => 'utf8mb4_unicode_ci',
    ];
}

function colCharset(): array { /** @phpstan-ignore-line */
    return [
        'api_applications' => [
            ['Token char(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Name varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'api_tokens' => [
            ['name varchar(40)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['token varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'applicant' => [
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'applicant_role' => [
            ['Title varchar(40)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artist_attr' => [
            ['name varchar(24)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['description varchar(500)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artist_discogs' => [
            ['stem varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['name varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artist_role' => [
            ['slug varchar(12)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['name varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['title varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['collection varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artist_usage' => [
            ["role enum('0','1','2','3','4','5','6','7')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artists_alias' => [
            ['Name varchar(200)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'artists_similar_votes' => [
            ["Way enum('up','down')", "NOT NULL DEFAULT 'up'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'better_transcode_music' => [
            ['edition varchar(255)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
        ],
        'blog' => [
            ['Title varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'bonus_item' => [
            ['Label varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Title varchar(64)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'bonus_pool' => [
            ['name varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'category' => [
            ["upload enum('audiobook','simple','music')", "NOT NULL DEFAULT 'simple'", 'utf8mb3', 'utf8mb3_general_ci'],
            ['name varchar(30)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
        ],
        'changelog' => [
            ['Message mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Author varchar(30)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'collage_attr' => [
            ['Name varchar(24)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description varchar(500)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'collages' => [
            ["Name varchar(100)", "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Deleted enum('0','1')", "DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Locked enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['TagList varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'comments' => [
            ["Page enum('artist','collages','requests','torrents')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'comments_edits' => [
            ["Page enum('forums','artist','collages','requests','torrents')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'contest' => [
            ['name varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['banner varchar(128)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['description longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'contest_has_bonus_pool' => [
            ["status enum('open','ready','paid')", "NOT NULL DEFAULT 'open'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'contest_type' => [
            ['name varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'cover_art' => [
            ['Image varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Summary varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'deleted_torrents' => [
            ["Media varchar(20)", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Format varchar(10)", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Encoding varchar(15)", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Remastered enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterTitle varchar(80)', "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterCatalogueNumber varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterRecordLabel varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Scene enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasLog enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasCue enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasLogDB enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["LogChecksum enum('0','1')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FileList longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["FilePath varchar(255)", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["FreeTorrent enum('0','1','2')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["FreeLeechType enum('0','1','2','3','4','5','6','7')", "NOT NULL", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Description mediumtext", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'deleted_torrents_group' => [
            ['Name varchar(300)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CatalogueNumber varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['TagList varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['WikiImage varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'do_not_upload' => [
            ['Name varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Comment varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'donations' => [
            ['Currency varchar(5)', "NOT NULL DEFAULT 'USD'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Source varchar(30)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Reason longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'donor_forum_usernames' => [
            ['Prefix varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Suffix varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'donor_rewards' => [
            ['IconMouseOverText varchar(200)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['AvatarMouseOverText varchar(200)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CustomIcon varchar(200)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['SecondAvatar varchar(200)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CustomIconLink varchar(200)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfo1 mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfo2 mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfo3 mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfo4 mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfoTitle1 varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfoTitle2 varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfoTitle3 varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ProfileInfoTitle4 varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'dupe_groups' => [
            ['Comments mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'email_blacklist' => [
            ['Email varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Comment mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'error_log' => [
            ['uri varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['trace mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'featured_albums' => [
            ['Title varchar(35)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums' => [
            ['Name varchar(40)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Description varchar(255)", "DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["AutoLock enum('0','1')", "DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_categories' => [
            ['Name varchar(40)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_polls' => [
            ['Question varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Answers mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Closed enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_posts' => [
            ['Body longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_topic_notes' => [
            ['Body longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_topics' => [
            ['Title varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["IsLocked enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["IsSticky enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'forums_transitions' => [
            ['label varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['permission_levels varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['permissions varchar(100)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['user_ids varchar(100)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'friends' => [
            ['Comment mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'group_log' => [
            ['Info longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'invite_source' => [
            ['name varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'invite_source_pending' => [
            ['invite_key varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'invites' => [
            ['InviteKey char(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Email varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Reason varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Notes varchar(2048)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'ip_bans' => [
            ['Reason varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'lastfm_users' => [
            ['Username varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'log' => [
            ['Message varchar(400)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'login_attempts' => [
            ['IP varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['capture varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'nav_items' => [
            ['tag varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['title varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['target varchar(200)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['tests varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'news' => [
            ['Title varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'payment_reminders' => [
            ['Text varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["cc enum('XBT','EUR','USD')", "NOT NULL DEFAULT 'USD'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'periodic_task' => [
            ['name varchar(64)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['classname varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['description varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'periodic_task_history' => [
            ["status enum('running','completed','failed')", "NOT NULL DEFAULT 'running'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'periodic_task_history_event' => [
            ["severity enum('debug','info','error')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['event varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'permissions' => [
            ['Name varchar(25)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['`Values` mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["DisplayStaff enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['PermittedForums varchar(150)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['badge varchar(5)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'phinxlog' => [
            ['migration_name varchar(100)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'pm_conversations' => [
            ['Subject varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'pm_conversations_users' => [
            ["InInbox enum('1','0')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["InSentbox enum('1','0')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["UnRead enum('1','0')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Sticky enum('1','0')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'pm_messages' => [
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'push_notifications_usage' => [
            ['PushService varchar(10)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'recovery_buffer' => [
            ['userclass varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'referral_accounts' => [
            ['Site varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['URL varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['User varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Password varchar(196)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Cookie varchar(1024)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'referral_users' => [
            ['Username varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Site varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IP varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['InviteKey varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'release_type' => [
            ['Name varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'reports' => [
            ['Type varchar(30)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Comment mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Status enum('New','InProgress','Resolved')", "DEFAULT 'New'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Reason mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Notes mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'reportsv2' => [
            ['Type varchar(20)', "DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['UserComment mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Status enum('New','InProgress','Resolved')", "DEFAULT 'New'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ModComment mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Track mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Image mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ExtraID mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Link mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['LogMessage mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'requests' => [
            ['Title varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Image varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CatalogueNumber varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['BitrateList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FormatList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['MediaList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['LogCue varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['OCLC varchar(55)', "DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'requests_artists' => [
            ["Importance enum('1','2','3','4','5','6','7','8')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'site_options' => [
            ['Name varchar(64)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Value text', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Comment mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_a' => [
            ['aname mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_delta' => [
            ['GroupName varchar(300)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ArtistName varchar(2048)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['TagList varchar(500)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CatalogueNumber varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Media varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Format varchar(10)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Encoding varchar(15)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterTitle varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterRecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterCatalogueNumber varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FileList longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_index_last_pos' => [
            ['Type varchar(16)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_requests' => [
            ['Title varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ArtistList mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CatalogueNumber varchar(50)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['BitrateList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FormatList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['MediaList varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['LogCue varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_t' => [
            ['media varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['format varchar(10)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['encoding varchar(15)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['remtitle varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['remrlabel varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['remcnumber varchar(80)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['filelist longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['description mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'sphinx_tg' => [
            ['name varchar(300)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['tags varchar(500)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['rlabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['cnumber varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'staff_blog' => [
            ['Title varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'staff_groups' => [
            ['Name mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'staff_pm_conversations' => [
            ['Subject varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Status enum('Open','Unanswered','Resolved')", "NOT NULL DEFAULT 'Unanswered'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'staff_pm_messages' => [
            ['Message mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'stylesheets' => [
            ['Name varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["`Default` enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["theme enum('dark','light')", "NOT NULL DEFAULT 'dark'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'tag_aliases' => [
            ['BadTag varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['AliasTag varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'tags' => [
            ['Name varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["TagType enum('genre','other')", "NOT NULL DEFAULT 'other'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'thread_note' => [
            ['Body longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Visibility enum('staff','public')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'thread_type' => [
            ['Name varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'top10_history' => [
            ["Type enum('Daily','Weekly')", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrent_attr' => [
            ['Name varchar(24)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description varchar(500)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrent_group_attr' => [
            ['Name varchar(24)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description varchar(500)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrent_report_configuration' => [
            ['type varchar(20)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
            ['name varchar(30)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
            ["need_image enum('none','optional','required','proof')", "NOT NULL DEFAULT 'none'", 'utf8mb3', 'utf8mb3_general_ci'],
            ["need_link enum('none','optional','required')", "NOT NULL DEFAULT 'none'", 'utf8mb3', 'utf8mb3_general_ci'],
            ["need_sitelink enum('none','optional','required')", "NOT NULL DEFAULT 'none'", 'utf8mb3', 'utf8mb3_general_ci'],
            ["need_track enum('none','optional','required','all')", "NOT NULL DEFAULT 'none'", 'utf8mb3', 'utf8mb3_general_ci'],
            ['resolve_log varchar(80)', 'utf8mb3', 'utf8mb3_general_ci'],
            ['explanation mediumtext', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
            ['pm_body mediumtext', 'utf8mb3', 'utf8mb3_general_ci'],
        ],
        'torrent_unseeded' => [
            ["state enum('never','unseeded')", "NOT NULL DEFAULT 'never'", 'utf8mb3', 'utf8mb3_general_ci'],
            ["notify enum('initial','final')", "NOT NULL DEFAULT 'initial'", 'utf8mb3', 'utf8mb3_general_ci'],
        ],
        'torrents' => [
            ['Media varchar(20)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Format varchar(10)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Encoding varchar(15)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Remastered enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterTitle varchar(80)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterCatalogueNumber varchar(80)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RemasterRecordLabel varchar(80)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Scene enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasLog enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasCue enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["HasLogDB enum('0','1')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["LogChecksum enum('0','1')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FileList longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FilePath varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["FreeTorrent enum('0','1','2')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["FreeLeechType enum('0','1','2','3','4','5','6','7')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrents_artists' => [
            ["Importance enum('1','2','3','4','5','6','7','8')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrents_group' => [
            ['Name varchar(300)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CatalogueNumber varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabel varchar(80)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['TagList varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['WikiBody mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['WikiImage varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrents_logs' => [
            ['FileName varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Details longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Checksum enum('0','1')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Adjusted enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["AdjustedChecksum enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['AdjustmentReason mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['AdjustmentDetails mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Ripper varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RipperVersion varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Language varchar(2)', "NOT NULL DEFAULT 'en'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["ChecksumState enum('checksum_ok','checksum_missing','checksum_invalid')", "NOT NULL DEFAULT 'checksum_ok'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['LogcheckerVersion varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'torrents_tags_votes' => [
            ["Way enum('up','down')", "NOT NULL DEFAULT 'up'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'user_attr' => [
            ['Name varchar(24)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Description varchar(500)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'user_ordinal' => [
            ['name varchar(32)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
            ['description varchar(500)', 'NOT NULL', 'utf8mb3', 'utf8mb3_general_ci'],
        ],
        'user_seedbox' => [
            ['name varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['useragent varchar(51)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_comments_last_read' => [
            ["Page enum('artist','collages','requests','torrents')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_enable_requests' => [
            ['Email varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IP varchar(15)', "NOT NULL DEFAULT '0.0.0.0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['UserAgent mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Token char(32)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_geodistribution' => [
            ['Code varchar(2)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_history_emails' => [
            ['Email varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IP varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['useragent varchar(768)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_history_ips' => [
            ['IP varchar(15)', "NOT NULL DEFAULT '0.0.0.0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_history_passkeys' => [
            ['OldPassKey varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['NewPassKey varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ChangerIP varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_history_passwords' => [
            ['ChangerIP varchar(15)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['useragent varchar(768)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_info' => [
            ['AdminComment mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['SiteOptions mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["TorrentGrouping enum('0','1','2')", "NOT NULL DEFAULT '0' COMMENT '0=Open,1=Closed,2=Off'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["BanReason enum('0','1','2','3','4')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RestrictedForums varchar(150)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['PermittedForums varchar(150)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['NavItems varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_main' => [
            ['Username varchar(20)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Email varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['PassHash varchar(60)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IRCKey char(32)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IP varchar(15)', "NOT NULL DEFAULT '0.0.0.0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['title varchar(1024)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Enabled enum('0','1','2','unconfirmed','enabled','disabled','banned')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Paranoia mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["Visible enum('0','1','yes','no')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['CustomPermissions mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['torrent_pass char(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ipcc varchar(2)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['2FA_Key varchar(16)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Recovery mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['auth_key varchar(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['avatar varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['profile_info text', "NOT NULL DEFAULT (_utf8mb4'')", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['profile_title varchar(255)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['slogan varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['stylesheet_url varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_notify_filters' => [
            ['Label varchar(128)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Artists longtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['RecordLabels text', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Users mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Tags varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['NotTags varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Categories varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Formats varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Encodings varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Media varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["ExcludeVA enum('1','0')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["NewGroupsOnly enum('1','0')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ReleaseTypes varchar(500)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_notify_quoted' => [
            ["Page enum('forums','artist','collages','requests','torrents')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_push_notifications' => [
            ['PushOptions mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_sessions' => [
            ['SessionID char(32)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ["KeepLogged enum('0','1')", "NOT NULL DEFAULT '0'", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Browser varchar(40)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['OperatingSystem varchar(13)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['IP varchar(15)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['FullUA mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['BrowserVersion varchar(40)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['OperatingSystemVersion varchar(40)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_subscriptions_comments' => [
            ["Page enum('artist','collages','requests','torrents')", 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_torrent_history' => [
            ["Finished enum('1','0')", "NOT NULL DEFAULT '1'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_votes' => [
            ["Type enum('Up','Down')", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'users_warnings_forums' => [
            ['Comment mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'wiki_aliases' => [
            ['Alias varchar(50)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'wiki_articles' => [
            ['Title varchar(100)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'wiki_artists' => [
            ['Body mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['summary varchar(400)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Image varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'wiki_revisions' => [
            ['Title varchar(255)', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Body mediumtext', 'NOT NULL', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'wiki_torrents' => [
            ['Body mediumtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Summary varchar(100)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['Image varchar(255)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'xbt_client_whitelist' => [
            ['peer_id varchar(20)', 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['vstring varchar(200)', 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'xbt_files_users' => [
            ['useragent varchar(51)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
            ['ip varchar(15)', "NOT NULL DEFAULT ''", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
        'xbt_forex' => [
            ["cc enum('EUR','USD')", "NOT NULL DEFAULT 'USD'", 'utf8mb4', 'utf8mb4_unicode_ci'],
        ],
    ];
}

final class TableCollation0900 extends AbstractMigration {
    public function up(): void {
        $this->query("
            DROP TABLE IF EXISTS donation_bitcoin
        ");
        foreach (array_keys(tableList()) as $tableName) {
            $this->query("
                ALTER TABLE $tableName COLLATE utf8mb4_0900_ai_ci
            ");
        }
        foreach (colCharset() as $tableName => $columnList) {
            $modify = implode(
                ', ',
                array_map(
                    function ($c) {
                        $default = count($c) == 4 ? $c[1] : '';
                        return "MODIFY {$c[0]} $default";
                    },
                    $columnList
                )
            );
            $this->query("
                ALTER TABLE $tableName $modify
            ");
        }
        $this->query("
            CREATE TABLE IF NOT EXISTS donations_bitcoin (
                BitcoinAddress varchar(34) NOT NULL,
                Amount decimal(24,8) NOT NULL,
                donations_bitcoin_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
                KEY BitcoinAddress (BitcoinAddress,Amount)
            )
        ");
        foreach (array_keys(tableList()) as $tableName) {
            $this->query("
                ALTER TABLE $tableName COLLATE utf8mb4_0900_ai_ci
            ");
        }
        foreach (colCharset() as $tableName => $columnList) {
            $modify = implode(
                ', ',
                array_map(
                    function ($c) {
                        $default = count($c) == 4 ? $c[1] : '';
                        return "MODIFY {$c[0]} $default";
                    },
                    $columnList
                )
            );
            $this->query("
                ALTER TABLE $tableName $modify
            ");
        }
        // artist_discogs.name is accent-sensitive to distinguish e.g. Avalon Ávalon
        $this->query("
            ALTER TABLE artist_discogs MODIFY name varchar(100) COLLATE utf8mb4_0900_as_ci NOT NULL
        ");
    }

    public function down(): void{
        foreach (tableList() as $tableName => $collate) {
            $this->query("
                ALTER TABLE $tableName COLLATE $collate
            ");
        }
        foreach (colCharset() as $tableName => $columnList) {
            $modify = implode(
                ', ',
                array_map(
                    function ($c) {
                        if (count($c) == 4) {
                            $charset = $c[2];
                            $collate = $c[3];
                            $default = $c[1];
                        } else {
                            $charset = $c[1];
                            $collate = $c[2];
                            $default = '';
                        }
                        return "MODIFY {$c[0]} CHARACTER SET $charset COLLATE $collate $default";
                    },
                    $columnList
                )
            );
            $this->query("
                ALTER TABLE $tableName $modify
            ");
        }
        $this->query("
            ALTER TABLE artist_discogs MODIFY name varchar(100) NOT NULL
        ");
    }
}
