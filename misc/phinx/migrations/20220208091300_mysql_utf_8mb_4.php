<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */

final class MysqlUtf8mb4 extends AbstractMigration {
    protected function table_list(): array {
        return (array)preg_split('/\s+/', 'api_applications api_tokens applicant
        applicant_role artist_attr artist_role artist_usage artists_alias
        artist_discogs artists_group artists_similar_votes bad_passwords blog
        bonus_item bonus_pool changelog collage_attr collages comments
        comments_edits contest contest_has_bonus_pool contest_type cover_art
        deleted_torrents deleted_torrents_group do_not_upload donations
        donations_bitcoin donor_forum_usernames donor_rewards dupe_groups
        email_blacklist error_log featured_albums forums forums_categories
        forums_polls forums_posts forums_topic_notes forums_topics
        forums_transitions friends group_log invite_source invite_source_pending
        invites ip_bans irc_channels label_aliases lastfm_users log
        login_attempts nav_items news payment_reminders periodic_task
        periodic_task_history periodic_task_history_event permissions phinxlog
        pm_conversations_users push_notifications_usage recovery_buffer
        referral_accounts referral_users release_type reports reportsv2 requests
        requests_artists site_options sphinx_a sphinx_delta
        sphinx_index_last_pos sphinx_requests sphinx_requests_delta sphinx_t
        sphinx_tg staff_blog staff_groups staff_pm_conversations stylesheets
        tag_aliases tags thread_note thread_type top10_history
        top10_history_torrents torrent_attr torrent_group_attr torrents
        torrents_artists torrents_logs torrents_tags_votes user_attr
        user_seedbox users_comments_last_read users_enable_requests
        users_geodistribution users_history_emails users_history_ips
        users_history_passkeys users_history_passwords users_info users_main
        users_notify_filters users_notify_quoted users_push_notifications
        users_sessions users_subscriptions_comments users_torrent_history
        users_votes users_warnings_forums wiki_aliases wiki_artists
        wiki_torrents xbt_client_whitelist xbt_files_users xbt_forex
        xbt_snatched');
    }

    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        foreach ($this->table_list() as $table) {
            $this->query("ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $this->query("ALTER TABLE artist_discogs
            MODIFY stem varchar(100) NOT NULL,
            MODIFY name varchar(100) NOT NULL
        ");
        $this->query("ALTER TABLE users_main
            MODIFY title varchar(1024)
        ");
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        foreach ($this->table_list() as $table) {
            $this->query("ALTER TABLE $table CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
        }
        $this->query("ALTER TABLE artist_discogs
            MODIFY stem varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            MODIFY name varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
        ");
        $this->query("ALTER TABLE users_main
            MODIFY title varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
        ");
    }
}
