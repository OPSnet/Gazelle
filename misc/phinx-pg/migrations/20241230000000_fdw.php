<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once __DIR__ . "/../../../lib/config.php";
// phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

final class Fdw extends AbstractMigration {
    protected array $enum = [ /** @phpstan-ignore-line */
        /*
        select concat('[''', table_name, '_', lower(column_name), '_t'', ''', column_type, '''],') as e
        from information_schema.columns
        where table_schema = 'gazelle' and column_type regexp '^enum';"
        */
        ['artist_usage_role_t', "enum('0','1','2','3','4','5','6','7')"],
        ['artists_similar_votes_way_t', "enum('up','down')"],
        ['category_upload_t', "enum('audiobook','simple','music')"],
        ['collages_deleted_t', "enum('0','1')"],
        ['collages_locked_t', "enum('0','1')"],
        ['comments_page_t', "enum('artist','collages','requests','torrents')"],
        ['comments_edits_page_t', "enum('forums','artist','collages','requests','torrents')"],
        ['contest_has_bonus_pool_status_t', "enum('open','ready','paid')"],
        ['deleted_torrents_remastered_t', "enum('0','1')"],
        ['deleted_torrents_scene_t', "enum('0','1')"],
        ['deleted_torrents_haslog_t', "enum('0','1')"],
        ['deleted_torrents_hascue_t', "enum('0','1')"],
        ['deleted_torrents_haslogdb_t', "enum('0','1')"],
        ['deleted_torrents_logchecksum_t', "enum('0','1')"],
        ['deleted_torrents_freetorrent_t', "enum('0','1','2')"],
        ['deleted_torrents_freeleechtype_t', "enum('0','1','2','3','4','5','6','7')"],
        ['forums_autolock_t', "enum('0','1')"],
        ['forums_polls_closed_t', "enum('0','1')"],
        ['forums_topics_islocked_t', "enum('0','1')"],
        ['forums_topics_issticky_t', "enum('0','1')"],
        ['payment_reminders_cc_t', "enum('XBT','EUR','USD')"],
        ['periodic_task_history_status_t', "enum('running','completed','failed')"],
        ['periodic_task_history_event_severity_t', "enum('debug','info','error')"],
        ['permissions_displaystaff_t', "enum('0','1')"],
        ['pm_conversations_users_ininbox_t', "enum('1','0')"],
        ['pm_conversations_users_insentbox_t', "enum('1','0')"],
        ['pm_conversations_users_unread_t', "enum('1','0')"],
        ['pm_conversations_users_sticky_t', "enum('1','0')"],
        ['reports_status_t', "enum('New','InProgress','Resolved')"],
        ['reportsv2_status_t', "enum('New','InProgress','Resolved')"],
        ['requests_artists_importance_t', "enum('1','2','3','4','5','6','7','8')"],
        ['staff_pm_conversations_status_t', "enum('Open','Unanswered','Resolved')"],
        ['stylesheets_default_t', "enum('0','1')"],
        ['stylesheets_theme_t', "enum('dark','light')"],
        ['tags_tagtype_t', "enum('genre','other')"],
        ['thread_note_visibility_t', "enum('staff','public')"],
        ['top10_history_type_t', "enum('Daily','Weekly')"],
        ['torrent_report_configuration_need_image_t', "enum('none','optional','required','proof')"],
        ['torrent_report_configuration_need_link_t', "enum('none','optional','required')"],
        ['torrent_report_configuration_need_sitelink_t', "enum('none','optional','required')"],
        ['torrent_report_configuration_need_track_t', "enum('none','optional','required','all')"],
        ['torrent_unseeded_state_t', "enum('never','unseeded')"],
        ['torrent_unseeded_notify_t', "enum('initial','final')"],
        ['torrents_remastered_t', "enum('0','1')"],
        ['torrents_scene_t', "enum('0','1')"],
        ['torrents_haslog_t', "enum('0','1')"],
        ['torrents_hascue_t', "enum('0','1')"],
        ['torrents_haslogdb_t', "enum('0','1')"],
        ['torrents_logchecksum_t', "enum('0','1')"],
        ['torrents_freetorrent_t', "enum('0','1','2')"],
        ['torrents_freeleechtype_t', "enum('0','1','2','3','4','5','6','7')"],
        ['torrents_artists_importance_t', "enum('1','2','3','4','5','6','7','8')"],
        ['torrents_logs_checksum_t', "enum('0','1')"],
        ['torrents_logs_adjusted_t', "enum('0','1')"],
        ['torrents_logs_adjustedchecksum_t', "enum('0','1')"],
        ['torrents_logs_checksumstate_t', "enum('checksum_ok','checksum_missing','checksum_invalid')"],
        ['torrents_tags_votes_way_t', "enum('up','down')"],
        ['users_comments_last_read_page_t', "enum('artist','collages','requests','torrents')"],
        ['users_info_torrentgrouping_t', "enum('0','1','2')"],
        ['users_info_banreason_t', "enum('0','1','2','3','4')"],
        ['users_main_enabled_t', "enum('0','1','2','unconfirmed','enabled','disabled','banned')"],
        ['users_main_visible_t', "enum('0','1','yes','no')"],
        ['users_notify_filters_excludeva_t', "enum('1','0')"],
        ['users_notify_filters_newgroupsonly_t', "enum('1','0')"],
        ['users_notify_quoted_page_t', "enum('forums','artist','collages','requests','torrents')"],
        ['users_sessions_keeplogged_t', "enum('0','1')"],
        ['users_subscriptions_comments_page_t', "enum('artist','collages','requests','torrents')"],
        ['users_torrent_history_finished_t', "enum('1','0')"],
        ['users_votes_type_t', "enum('Up','Down')"],
        ['xbt_forex_cc_t', "enum('EUR','USD')"],
    ];

    public function up(): void {
        $host    = SQLHOST;
        $port    = SQLPORT;
        $db      = SQLDB;
        $login   = SQLLOGIN;
        $pass    = SQLPASS;
        $pg_user = GZPG_USER;

        $this->query("
            create server relayer
            foreign data wrapper mysql_fdw
            options (
                host '$host',
                port '$port'
            )
        ");
        $this->query("
            create user mapping for $pg_user
            server relayer
            options (
                username '$login',
                password '$pass'
            )
        ");

        foreach ($this->enum as $e) {
            $this->query("
                create type {$e[0]} as {$e[1]}
            ");
        }

        $this->query("
            create schema if not exists relay authorization $pg_user
        ");
        $this->query("
            import foreign schema $db from server relayer into relay;
        ");
    }

    public function down(): void {
        $this->query("
            drop schema if exists relay cascade
        ");
        foreach ($this->enum as $e) {
            $this->query("drop type {$e[0]}");
        }
        $this->query("
            drop server relayer cascade
        ");
    }
}
