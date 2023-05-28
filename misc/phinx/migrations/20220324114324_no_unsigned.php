<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NoUnsigned extends AbstractMigration {
    public function change(): void {
        $this->query("alter table applicant drop constraint applicant_ibfk_1");
        $this->query("alter table applicant drop constraint applicant_ibfk_2");
        $this->query("alter table applicant drop constraint applicant_ibfk_3");
        $this->query("alter table applicant_role drop constraint applicant_role_ibfk_1");
        $this->query("alter table artist_discogs drop constraint artist_discogs_ibfk_2");
        $this->query("alter table bonus_history drop constraint bonus_history_fk_user");
        $this->query("alter table bonus_history drop constraint bonus_history_fk_item");
        $this->query("alter table bonus_pool_contrib drop constraint bonus_pool_contrib_ibfk_1");
        $this->query("alter table bonus_pool_contrib drop constraint bonus_pool_contrib_ibfk_2");
        $this->query("alter table bookmarks_collages drop constraint bookmarks_collages_ibfk_2");
        $this->query("alter table bookmarks_torrents drop constraint bookmarks_torrents_ibfk_2");
        $this->query("alter table collages drop constraint collages_ibfk_1");
        $this->query("alter table collages_artists drop constraint collages_artists_ibfk_3");
        $this->query("alter table collages_torrents drop constraint collages_torrents_ibfk_3");
        $this->query("alter table contest_has_bonus_pool drop constraint contest_has_bonus_pool_ibfk_1");
        $this->query("alter table contest_has_bonus_pool drop constraint contest_has_bonus_pool_ibfk_2");
        $this->query("alter table forums_last_read_topics drop constraint forums_last_read_topics_ibfk_3");
        $this->query("alter table forums_posts drop foreign key forums_posts_ibfk_1");
        $this->query("alter table forums_topics drop constraint forums_topics_ibfk_1");
        $this->query("alter table forums_topics drop constraint forums_topics_ibfk_2");
        $this->query("alter table forums_topics drop constraint forums_topics_ibfk_3");
        $this->query("alter table forums_topics drop constraint forums_topics_ibfk_4");
        $this->query("alter table forums_transitions drop constraint forums_transitions_ibfk_1");
        $this->query("alter table forums_transitions drop constraint forums_transitions_ibfk_2");
        $this->query("alter table invite_source_pending drop constraint invite_source_pending_ibfk_1");
        $this->query("alter table invite_source_pending drop constraint invite_source_pending_ibfk_2");
        $this->query("alter table inviter_has_invite_source drop constraint inviter_has_invite_source_ibfk_1");
        $this->query("alter table inviter_has_invite_source drop constraint inviter_has_invite_source_ibfk_2");
        $this->query("alter table locked_accounts drop constraint locked_accounts_fk");
        $this->query("alter table periodic_task_history drop constraint periodic_task_history_ibfk_1");
        $this->query("alter table periodic_task_history_event drop constraint periodic_task_history_event_ibfk_1");
        $this->query("alter table permission_rate_limit drop constraint permission_rate_limit_ibfk_1");
        $this->query("alter table permissions drop constraint permissions_ibfk_1");
        $this->query("alter table ratelimit_torrent drop constraint ratelimit_torrent_ibfk_1");
        $this->query("alter table ratelimit_torrent drop constraint ratelimit_torrent_ibfk_2");
        $this->query("alter table staff_blog_visits drop constraint staff_blog_visits_ibfk_1");
        $this->query("alter table thread drop constraint thread_ibfk_1");
        $this->query("alter table thread_note drop constraint thread_note_ibfk_1");
        $this->query("alter table thread_note drop constraint thread_note_ibfk_2");
        $this->query("alter table torrent_has_attr drop constraint torrent_has_attr_ibfk_2;");
        $this->query("alter table torrents drop constraint torrents_ibfk_1");
        $this->query("alter table torrents drop constraint torrents_ibfk_2");
        $this->query("alter table torrents_artists drop constraint torrents_artists_ibfk_1");
        $this->query("alter table torrents_leech_stats drop constraint torrents_leech_stats_ibfk_1");
        $this->query("alter table user_bonus drop constraint user_bonus_ibfk_1");
        $this->query("alter table user_flt drop constraint user_flt_ibfk_1");
        $this->query("alter table user_has_attr drop constraint user_has_attr_ibfk_2");
        $this->query("alter table user_has_invite_source drop constraint user_has_invite_source_ibfk_1");
        $this->query("alter table user_has_invite_source drop constraint user_has_invite_source_ibfk_2");
        $this->query("alter table user_last_access drop constraint user_last_access_ibfk_1");
        $this->query("alter table user_read_blog drop constraint user_read_blog_ibfk_1");
        $this->query("alter table user_read_blog drop constraint user_read_blog_ibfk_2");
        $this->query("alter table user_read_forum drop constraint user_read_forum_ibfk_1");
        $this->query("alter table user_read_news drop constraint user_read_news_ibfk_1");
        $this->query("alter table user_read_news drop constraint user_read_news_ibfk_2");
        $this->query("alter table user_seedbox drop constraint user_seedbox_ibfk_1");
        $this->query("alter table user_summary drop constraint user_summary_ibfk_1");
        $this->query("alter table users_collage_subs drop constraint users_collage_subs_ibfk_2");
        $this->query("alter table users_dupes drop constraint users_dupes_ibfk_1");
        $this->query("alter table users_dupes drop constraint users_dupes_ibfk_2");
        $this->query("alter table users_enable_requests drop constraint users_enable_requests_ibfk_1");
        $this->query("alter table users_enable_requests drop constraint users_enable_requests_ibfk_2");
        $this->query("alter table users_info drop constraint users_info_ibfk_1");
        $this->query("alter table users_leech_stats drop constraint users_leech_stats_ibfk_1");
        $this->query("alter table users_stats_daily drop constraint users_stats_daily_ibfk_1");
        $this->query("alter table users_stats_monthly drop constraint users_stats_monthly_ibfk_1");
        $this->query("alter table users_stats_yearly drop constraint users_stats_yearly_ibfk_1");
        $this->query("alter table users_votes drop constraint users_votes_ibfk_2");

        $this->query("ALTER TABLE `api_tokens`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `applicant`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `RoleID` int NOT NULL,
          modify `UserID` int NOT NULL,
          modify `ThreadID` int NOT NULL");

        $this->query("ALTER TABLE `applicant_role`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `artist_discogs`
          modify `artist_discogs_id` int NOT NULL,
          modify `user_id` int NOT NULL,
          modify `sequence` tinyint NOT NULL");

        $this->query("ALTER TABLE `artist_usage`
          modify `uses` int NOT NULL");

        $this->query("ALTER TABLE `artists_alias`
          modify `UserID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `blog`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL,
          modify `ThreadID` int DEFAULT NULL");

        $this->query("ALTER TABLE `bonus_history`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `ItemID` int NOT NULL,
          modify `UserID` int NOT NULL,
          modify `Price` int NOT NULL,
          modify `OtherUserID` int DEFAULT NULL");

        $this->query("ALTER TABLE `bonus_item`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Price` int NOT NULL,
          modify `Amount` int DEFAULT NULL,
          modify `MinClass` int NOT NULL DEFAULT '0',
          modify `FreeClass` int NOT NULL DEFAULT '999999',
          modify `sequence` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `bonus_pool`
          modify `bonus_pool_id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `bonus_pool_contrib`
          modify `bonus_pool_contrib_id` int NOT NULL AUTO_INCREMENT,
          modify `bonus_pool_id` int NOT NULL,
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `bookmarks_collages`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `bookmarks_torrents`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `collages`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `collages_artists`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `collages_torrents`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `contest_has_bonus_pool`
          modify `bonus_pool_id` int NOT NULL,
          modify `bonus_contest` int NOT NULL DEFAULT '15',
          modify `bonus_user` int NOT NULL DEFAULT '5',
          modify `bonus_per_entry` int NOT NULL DEFAULT '80'");

        $this->query("ALTER TABLE `deleted_torrents`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `deleted_torrents_leech_stats`
          modify `Seeders` int NOT NULL DEFAULT '0',
          modify `Leechers` int NOT NULL DEFAULT '0',
          modify `Snatched` int NOT NULL DEFAULT '0',
          modify `Balance` bigint NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `donations`
          modify `donations_id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `dupe_groups`
          modify `ID` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `error_log`
          modify `error_log_id` int NOT NULL AUTO_INCREMENT,
          modify `memory` bigint NOT NULL DEFAULT '0',
          modify `nr_query` int NOT NULL DEFAULT '0',
          modify `nr_cache` int NOT NULL DEFAULT '0',
          modify `seen` int NOT NULL DEFAULT '1'");

        $this->query("ALTER TABLE `forums`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Sort` int NOT NULL,
          modify `LastPostAuthorID` int NOT NULL,
          modify `AutoLockWeeks` int NOT NULL DEFAULT '4'");

        $this->query("ALTER TABLE `forums_categories`
          modify `Sort` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `forums_last_read_topics`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `forums_polls_votes`
          modify `TopicID` int NOT NULL,
          modify `UserID` int NOT NULL,
          modify `Vote` tinyint NOT NULL");

        $this->query("ALTER TABLE `forums_posts`
          modify `AuthorID` int NOT NULL,
          modify `EditedUserID` int DEFAULT NULL");

        $this->query("ALTER TABLE `forums_topics`
          modify `AuthorID` int NOT NULL,
          modify `ForumID` int NOT NULL,
          modify `LastPostAuthorID` int NOT NULL");

        $this->query("ALTER TABLE `forums_transitions`
          modify `source` int NOT NULL,
          modify `destination` int NOT NULL,
          modify `permission_class` int NOT NULL DEFAULT '800'");

        $this->query("ALTER TABLE `friends`
          modify `UserID` int NOT NULL,
          modify `FriendID` int NOT NULL");

        $this->query("ALTER TABLE `invite_source`
          modify `invite_source_id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `invite_source_pending`
          modify `user_id` int NOT NULL,
          modify `invite_source_id` int NOT NULL");

        $this->query("ALTER TABLE `inviter_has_invite_source`
          modify `user_id` int NOT NULL,
          modify `invite_source_id` int NOT NULL");

        $this->query("ALTER TABLE `ip_bans`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `FromIP` int NOT NULL,
          modify `ToIP` int NOT NULL,
          modify `user_id` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `irc_channels`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `MinLevel` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `lastfm_users`
          modify `ID` int NOT NULL");

        $this->query("ALTER TABLE `locked_accounts`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `log`
          modify `ID` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `login_attempts`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL DEFAULT '0',
          modify `Attempts` int NOT NULL DEFAULT '1'");

        $this->query("ALTER TABLE `nav_items`
          modify `id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `news`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `payment_reminders`
          modify `ID` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `periodic_task`
          modify `periodic_task_id` int NOT NULL AUTO_INCREMENT,
          modify `period` int NOT NULL");

        $this->query("ALTER TABLE `periodic_task_history`
          modify `periodic_task_history_id` int NOT NULL AUTO_INCREMENT,
          modify `periodic_task_id` int NOT NULL,
          modify `num_errors` int NOT NULL DEFAULT '0',
          modify `num_items` int NOT NULL DEFAULT '0',
          modify `duration_ms` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `periodic_task_history_event`
          modify `periodic_task_history_event_id` int NOT NULL AUTO_INCREMENT,
          modify `periodic_task_history_id` int NOT NULL,
          modify `reference` int NOT NULL");

        $this->query("ALTER TABLE `permission_rate_limit`
          modify `permission_id` int NOT NULL");

        $this->query("ALTER TABLE `permissions`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Level` int NOT NULL,
          modify `StaffGroup` int DEFAULT NULL");

        $this->query("ALTER TABLE `ratelimit_torrent`
          modify `ratelimit_torrent_id` int NOT NULL AUTO_INCREMENT,
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `recovery_buffer`
          modify `user_id` int NOT NULL AUTO_INCREMENT,
          modify `prev_id` int NOT NULL");

        $this->query("ALTER TABLE `referral_accounts`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Type` int NOT NULL");

        $this->query("ALTER TABLE `referral_users`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `reports`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL DEFAULT '0',
          modify `ThingID` int NOT NULL DEFAULT '0',
          modify `ResolverID` int NOT NULL DEFAULT '0',
          modify `ClaimerID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `reportsv2`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `ReporterID` int NOT NULL DEFAULT '0',
          modify `TorrentID` int NOT NULL DEFAULT '0',
          modify `ResolverID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `requests`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL DEFAULT '0',
          modify `FillerID` int NOT NULL DEFAULT '0',
          modify `TorrentID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `requests_artists`
          modify `RequestID` int NOT NULL");

        $this->query("ALTER TABLE `requests_votes`
          modify `Bounty` bigint NOT NULL");

        $this->query("ALTER TABLE `sphinx_requests`
          modify `ID` int NOT NULL,
          modify `UserID` int NOT NULL DEFAULT '0',
          modify `TimeAdded` int NOT NULL DEFAULT '0',
          modify `LastVote` int NOT NULL DEFAULT '0',
          modify `FillerID` int NOT NULL DEFAULT '0',
          modify `TorrentID` int NOT NULL DEFAULT '0',
          modify `TimeFilled` int DEFAULT NULL,
          modify `Bounty` bigint NOT NULL DEFAULT '0',
          modify `Votes` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `sphinx_requests_delta`
          modify `ID` int NOT NULL,
          modify `UserID` int NOT NULL DEFAULT '0',
          modify `TimeAdded` int DEFAULT NULL,
          modify `LastVote` int DEFAULT NULL,
          modify `FillerID` int NOT NULL DEFAULT '0',
          modify `TorrentID` int NOT NULL DEFAULT '0',
          modify `TimeFilled` int DEFAULT NULL,
          modify `Bounty` bigint NOT NULL DEFAULT '0',
          modify `Votes` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `sphinx_t`
          modify `remident` int NOT NULL");

        $this->query("ALTER TABLE `staff_blog`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `staff_blog_visits`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `staff_groups`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Sort` int NOT NULL");

        $this->query("ALTER TABLE `stylesheets`
          modify `ID` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `thread`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `ThreadTypeID` int NOT NULL");

        $this->query("ALTER TABLE `thread_note`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `ThreadID` int NOT NULL,
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `thread_type`
          modify `ID` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `torrents`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `torrents_artists`
          modify `UserID` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `torrents_leech_stats`
          modify `Seeders` int NOT NULL DEFAULT '0',
          modify `Leechers` int NOT NULL DEFAULT '0',
          modify `Snatched` int NOT NULL DEFAULT '0',
          modify `Balance` bigint NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `torrents_votes`
          modify `Ups` int NOT NULL DEFAULT '0',
          modify `Total` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `user_bonus`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `user_flt`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `user_has_attr`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `user_has_invite_source`
          modify `user_id` int NOT NULL,
          modify `invite_source_id` int NOT NULL");

        $this->query("ALTER TABLE `user_last_access`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `user_read_blog`
          modify `user_id` int NOT NULL,
          modify `blog_id` int NOT NULL");

        $this->query("ALTER TABLE `user_read_forum`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `user_read_news`
          modify `user_id` int NOT NULL,
          modify `news_id` int NOT NULL");

        $this->query("ALTER TABLE `user_seedbox`
          modify `user_seedbox_id` int NOT NULL AUTO_INCREMENT,
          modify `user_id` int NOT NULL,
          modify `ipaddr` int NOT NULL");

        $this->query("ALTER TABLE `user_summary`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `user_torrent_remove`
          modify `user_id` int NOT NULL");

        $this->query("ALTER TABLE `users_collage_subs`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_dupes`
          modify `GroupID` int NOT NULL,
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_enable_requests`
          modify `UserID` int NOT NULL,
          modify `CheckedBy` int DEFAULT NULL");

        $this->query("ALTER TABLE `users_history_emails`
          modify `users_history_emails_id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `users_info`
          modify `UserID` int NOT NULL,
          modify `StyleID` int NOT NULL,
          modify `RatioWatchDownload` bigint NOT NULL DEFAULT '0',
          modify `RatioWatchTimes` tinyint NOT NULL DEFAULT '0',
          modify `collages` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `users_leech_stats`
          modify `UserID` int NOT NULL,
          modify `Uploaded` bigint NOT NULL DEFAULT '0',
          modify `Downloaded` bigint NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `users_levels`
          modify `UserID` int NOT NULL,
          modify `PermissionID` int NOT NULL");

        $this->query("ALTER TABLE `users_main`
          modify `ID` int NOT NULL AUTO_INCREMENT,
          modify `Invites` int NOT NULL DEFAULT '0',
          modify `PermissionID` int NOT NULL");

        $this->query("ALTER TABLE `users_stats_daily`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_stats_monthly`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_stats_yearly`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_torrent_history`
          modify `UserID` int NOT NULL,
          modify `NumTorrents` int NOT NULL,
          modify `Date` int NOT NULL,
          modify `Time` int NOT NULL DEFAULT '0',
          modify `LastTime` int NOT NULL DEFAULT '0',
          modify `Weight` bigint NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `users_votes`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `users_warnings_forums`
          modify `UserID` int NOT NULL");

        $this->query("ALTER TABLE `wiki_revisions`
          modify `Author` int NOT NULL");

        $this->query("ALTER TABLE `xbt_client_whitelist`
          modify `id` int NOT NULL AUTO_INCREMENT");

        $this->query("ALTER TABLE `xbt_files_users`
          modify `upspeed` int NOT NULL DEFAULT '0',
          modify `downspeed` int NOT NULL DEFAULT '0',
          modify `timespent` int NOT NULL DEFAULT '0'");

        $this->query("ALTER TABLE `xbt_forex`
          modify `btc_forex_id` int NOT NULL AUTO_INCREMENT");

        $this->query("alter table applicant add foreign key (RoleID) references applicant_role (ID)");
        $this->query("alter table applicant add foreign key (ThreadID) references thread (ID)");
        $this->query("alter table applicant add foreign key (UserID) references users_main (ID)");
        $this->query("alter table applicant_role add foreign key (UserID) references users_main (ID)");
        $this->query("alter table artist_discogs add foreign key (user_id) references users_main (ID)");
        $this->query("alter table bonus_history add foreign key (UserID) references users_main (ID)");
        $this->query("alter table bonus_history add foreign key (ItemID) references bonus_item (ID)");
        $this->query("alter table bonus_pool_contrib add foreign key (user_id) references users_main (ID)");
        $this->query("alter table bonus_pool_contrib add foreign key (bonus_pool_id) references bonus_pool (bonus_pool_id)");
        $this->query("alter table bookmarks_collages add foreign key (UserID) references users_main (ID)");
        $this->query("alter table bookmarks_torrents add foreign key (UserID) references users_main (ID)");
        $this->query("alter table collages add foreign key (UserID) references users_main (ID)");
        $this->query("alter table collages_artists add foreign key (UserID) references users_main (ID)");
        $this->query("alter table collages_torrents add foreign key (UserID) references users_main (ID)");
        $this->query("alter table contest_has_bonus_pool add foreign key (bonus_pool_id) references bonus_pool (bonus_pool_id)");
        $this->query("alter table contest_has_bonus_pool add foreign key (contest_id) references contest (contest_id)");
        $this->query("alter table forums_last_read_topics add foreign key (UserID) references users_main (ID)");
        $this->query("alter table forums_posts add foreign key (TopicID) references forums_topics (ID)");
        $this->query("alter table forums_topics add foreign key (AuthorID) references users_main (ID)");
        $this->query("alter table forums_topics add foreign key (LastPostAuthorID) references users_main (ID)");
        $this->query("alter table forums_topics add foreign key (ForumID) references forums (ID)");
        $this->query("alter table forums_topics add foreign key (LastPostID) references forums_posts (ID)");
        $this->query("alter table forums_transitions add foreign key (source) references forums (ID)");
        $this->query("alter table forums_transitions add foreign key (destination) references forums (ID)");
        $this->query("alter table invite_source_pending add foreign key (user_id) references users_main (ID)");
        $this->query("alter table invite_source_pending add foreign key (invite_source_id) references invite_source (invite_source_id)");
        $this->query("alter table inviter_has_invite_source add foreign key (user_id) references users_main (ID)");
        $this->query("alter table inviter_has_invite_source add foreign key (invite_source_id) references invite_source (invite_source_id)");
        $this->query("alter table locked_accounts add foreign key (UserID) references users_main (ID)");
        $this->query("alter table periodic_task_history add foreign key (periodic_task_id) references periodic_task (periodic_task_id)");
        $this->query("alter table periodic_task_history_event add foreign key (periodic_task_history_id) references periodic_task_history (periodic_task_history_id)");
        $this->query("alter table permission_rate_limit add foreign key (permission_id) references permissions (ID)");
        $this->query("alter table permissions add foreign key (StaffGroup) references staff_groups (ID)");
        $this->query("alter table ratelimit_torrent add foreign key (user_id) references users_main (ID)");
        $this->query("alter table ratelimit_torrent add foreign key (torrent_id) references torrents (ID)");
        $this->query("alter table staff_blog_visits add foreign key (UserID) references users_main (ID)");
        $this->query("alter table thread add foreign key (ThreadTypeID) references thread_type (ID)");
        $this->query("alter table thread_note add foreign key (UserID) references users_main (ID)");
        $this->query("alter table thread_note add foreign key (ThreadID) references thread (ID)");
        $this->query("alter table torrent_has_attr add foreign key (TorrentID) references torrents (ID)");
        $this->query("alter table torrents add foreign key (GroupID) references torrents_group (ID)");
        $this->query("alter table torrents add foreign key (UserID) references users_main (ID)");
        $this->query("alter table torrents_artists add foreign key (artist_role_id) references artist_role (artist_role_id)");
        $this->query("alter table torrents_leech_stats add foreign key (TorrentID) references torrents (ID)");
        $this->query("alter table user_bonus add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_flt add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_has_attr add foreign key (UserID) references users_main (ID)");
        $this->query("alter table user_has_invite_source add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_has_invite_source add foreign key (invite_source_id) references invite_source (invite_source_id)");
        $this->query("alter table user_last_access add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_read_blog add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_read_blog add foreign key (blog_id) references blog (ID)");
        $this->query("alter table user_read_forum add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_read_news add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_read_news add foreign key (news_id) references news (ID)");
        $this->query("alter table user_seedbox add foreign key (user_id) references users_main (ID)");
        $this->query("alter table user_summary add foreign key (user_id) references users_main (ID)");
        $this->query("alter table users_collage_subs add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_dupes add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_dupes add foreign key (GroupID) references dupe_groups (ID)");
        $this->query("alter table users_enable_requests add foreign key (CheckedBy) references users_main (ID)");
        $this->query("alter table users_enable_requests add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_info add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_leech_stats add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_stats_daily add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_stats_monthly add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_stats_yearly add foreign key (UserID) references users_main (ID)");
        $this->query("alter table users_votes add foreign key (UserID) references users_main (ID)");
    }
}
