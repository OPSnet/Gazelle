<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NoUnsigned extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->query("
ALTER TABLE `api_tokens`
  modify `user_id` int NOT NULL;

ALTER TABLE `applicant`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `RoleID` int NOT NULL,
  modify `UserID` int NOT NULL,
  modify `ThreadID` int NOT NULL;

ALTER TABLE `applicant_role`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL;

ALTER TABLE `artist_discogs`
  modify `artist_discogs_id` int NOT NULL,
  modify `user_id` int NOT NULL,
  modify `sequence` tinyint NOT NULL;

ALTER TABLE `artist_usage`
  modify `uses` int NOT NULL;

ALTER TABLE `artists_alias`
  modify `UserID` int NOT NULL DEFAULT '0';

ALTER TABLE `blog`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL,
  modify `ThreadID` int DEFAULT NULL;

ALTER TABLE `bonus_history`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `ItemID` int NOT NULL,
  modify `UserID` int NOT NULL,
  modify `Price` int NOT NULL,
  modify `OtherUserID` int DEFAULT NULL;

ALTER TABLE `bonus_item`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Price` int NOT NULL,
  modify `Amount` int DEFAULT NULL,
  modify `MinClass` int NOT NULL DEFAULT '0',
  modify `FreeClass` int NOT NULL DEFAULT '999999',
  modify `sequence` int NOT NULL DEFAULT '0';

ALTER TABLE `bonus_pool`
  modify `bonus_pool_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `bonus_pool_contrib`
  modify `bonus_pool_contrib_id` int NOT NULL AUTO_INCREMENT,
  modify `bonus_pool_id` int NOT NULL,
  modify `user_id` int NOT NULL;

ALTER TABLE `bookmarks_collages`
  modify `UserID` int NOT NULL;

ALTER TABLE `bookmarks_torrents`
  modify `UserID` int NOT NULL;

ALTER TABLE `collages`
  modify `UserID` int NOT NULL;

ALTER TABLE `collages_artists`
  modify `UserID` int NOT NULL;

ALTER TABLE `collages_torrents`
  modify `UserID` int NOT NULL;

ALTER TABLE `contest_has_bonus_pool`
  modify `bonus_pool_id` int NOT NULL,
  modify `bonus_contest` int NOT NULL DEFAULT '15',
  modify `bonus_user` int NOT NULL DEFAULT '5',
  modify `bonus_per_entry` int NOT NULL DEFAULT '80';

ALTER TABLE `deleted_torrents`
  modify `UserID` int NOT NULL;

ALTER TABLE `deleted_torrents_leech_stats`
  modify `Seeders` int NOT NULL DEFAULT '0',
  modify `Leechers` int NOT NULL DEFAULT '0',
  modify `Snatched` int NOT NULL DEFAULT '0',
  modify `Balance` bigint NOT NULL DEFAULT '0';

ALTER TABLE `donations`
  modify `donations_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `dupe_groups`
  modify `ID` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `error_log`
  modify `error_log_id` int NOT NULL AUTO_INCREMENT,
  modify `memory` bigint NOT NULL DEFAULT '0',
  modify `nr_query` int NOT NULL DEFAULT '0',
  modify `nr_cache` int NOT NULL DEFAULT '0',
  modify `seen` int NOT NULL DEFAULT '1';

ALTER TABLE `forums`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Sort` int NOT NULL,
  modify `LastPostAuthorID` int NOT NULL,
  modify `AutoLockWeeks` int NOT NULL DEFAULT '4';

ALTER TABLE `forums_categories`
  modify `Sort` int NOT NULL DEFAULT '0';

ALTER TABLE `forums_last_read_topics`
  modify `UserID` int NOT NULL;

ALTER TABLE `forums_polls_votes`
  modify `TopicID` int NOT NULL,
  modify `UserID` int NOT NULL,
  modify `Vote` tinyint NOT NULL;

ALTER TABLE `forums_posts`
  modify `AuthorID` int NOT NULL,
  modify `EditedUserID` int DEFAULT NULL;

ALTER TABLE `forums_topics`
  modify `ForumID` int NOT NULL,
  modify `LastPostAuthorID` int NOT NULL;

ALTER TABLE `forums_transitions`
  modify `source` int NOT NULL,
  modify `destination` int NOT NULL,
  modify `permission_class` int NOT NULL DEFAULT '800';

ALTER TABLE `friends`
  modify `UserID` int NOT NULL,
  modify `FriendID` int NOT NULL;

ALTER TABLE `image_map_user`
  modify `image_map_id` int NOT NULL,
  modify `user_id` int NOT NULL;

ALTER TABLE `invite_source`
  modify `invite_source_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `invite_source_pending`
  modify `invite_source_id` int NOT NULL;

ALTER TABLE `inviter_has_invite_source`
  modify `user_id` int NOT NULL,
  modify `invite_source_id` int NOT NULL;

ALTER TABLE `ip_bans`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `FromIP` int NOT NULL,
  modify `ToIP` int NOT NULL,
  modify `user_id` int NOT NULL DEFAULT '0';

ALTER TABLE `irc_channels`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `MinLevel` int NOT NULL DEFAULT '0';

ALTER TABLE `lastfm_users`
  modify `ID` int NOT NULL;

ALTER TABLE `locked_accounts`
  modify `UserID` int NOT NULL;

ALTER TABLE `log`
  modify `ID` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `login_attempts`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL DEFAULT '0',
  modify `Attempts` int NOT NULL DEFAULT '1';

ALTER TABLE `nav_items`
  modify `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `news`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL;

ALTER TABLE `payment_reminders`
  modify `ID` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `periodic_task`
  modify `periodic_task_id` int NOT NULL AUTO_INCREMENT,
  modify `period` int NOT NULL;

ALTER TABLE `periodic_task_history`
  modify `periodic_task_history_id` int NOT NULL AUTO_INCREMENT,
  modify `periodic_task_id` int NOT NULL,
  modify `num_errors` int NOT NULL DEFAULT '0',
  modify `num_items` int NOT NULL DEFAULT '0',
  modify `duration_ms` int NOT NULL DEFAULT '0';

ALTER TABLE `periodic_task_history_event`
  modify `periodic_task_history_event_id` int NOT NULL AUTO_INCREMENT,
  modify `periodic_task_history_id` int NOT NULL,
  modify `reference` int NOT NULL;

ALTER TABLE `permission_rate_limit`
  modify `permission_id` int NOT NULL;

ALTER TABLE `permissions`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Level` int NOT NULL,
  modify `StaffGroup` int DEFAULT NULL;

ALTER TABLE `ratelimit_torrent`
  modify `ratelimit_torrent_id` int NOT NULL AUTO_INCREMENT,
  modify `user_id` int NOT NULL;

ALTER TABLE `recovery`
  modify `recovery_id` int NOT NULL AUTO_INCREMENT,
  modify `admin_user_id` int DEFAULT NULL;

ALTER TABLE `recovery_buffer`
  modify `user_id` int NOT NULL AUTO_INCREMENT,
  modify `prev_id` int NOT NULL;

ALTER TABLE `referral_accounts`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Type` int NOT NULL;

ALTER TABLE `referral_users`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL DEFAULT '0';

ALTER TABLE `reports`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL DEFAULT '0',
  modify `ThingID` int NOT NULL DEFAULT '0',
  modify `ResolverID` int NOT NULL DEFAULT '0',
  modify `ClaimerID` int NOT NULL DEFAULT '0';

ALTER TABLE `reportsv2`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `ReporterID` int NOT NULL DEFAULT '0',
  modify `TorrentID` int NOT NULL DEFAULT '0',
  modify `ResolverID` int NOT NULL DEFAULT '0';

ALTER TABLE `requests`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL DEFAULT '0',
  modify `FillerID` int NOT NULL DEFAULT '0',
  modify `TorrentID` int NOT NULL DEFAULT '0';

ALTER TABLE `requests_artists`
  modify `RequestID` int NOT NULL;

ALTER TABLE `requests_votes`
  modify `Bounty` bigint NOT NULL;

ALTER TABLE `sphinx_requests`
  modify `ID` int NOT NULL,
  modify `UserID` int NOT NULL DEFAULT '0',
  modify `TimeAdded` int NOT NULL DEFAULT '0',
  modify `LastVote` int NOT NULL DEFAULT '0',
  modify `FillerID` int NOT NULL DEFAULT '0',
  modify `TorrentID` int NOT NULL DEFAULT '0',
  modify `TimeFilled` int DEFAULT NULL,
  modify `Bounty` bigint NOT NULL DEFAULT '0',
  modify `Votes` int NOT NULL DEFAULT '0';

ALTER TABLE `sphinx_requests_delta`
  modify `ID` int NOT NULL,
  modify `UserID` int NOT NULL DEFAULT '0',
  modify `TimeAdded` int DEFAULT NULL,
  modify `LastVote` int DEFAULT NULL,
  modify `FillerID` int NOT NULL DEFAULT '0',
  modify `TorrentID` int NOT NULL DEFAULT '0',
  modify `TimeFilled` int DEFAULT NULL,
  modify `Bounty` bigint NOT NULL DEFAULT '0',
  modify `Votes` int NOT NULL DEFAULT '0';

ALTER TABLE `sphinx_t`
  modify `remident` int NOT NULL;

ALTER TABLE `staff_blog`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `UserID` int NOT NULL;

ALTER TABLE `staff_blog_visits`
  modify `UserID` int NOT NULL;

ALTER TABLE `staff_groups`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Sort` int NOT NULL;

ALTER TABLE `stylesheets`
  modify `ID` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `thread`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `ThreadTypeID` int NOT NULL;

ALTER TABLE `thread_note`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `ThreadID` int NOT NULL,
  modify `UserID` int NOT NULL;

ALTER TABLE `thread_type`
  modify `ID` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `torrent_unseeded_claim`
  modify `user_id` int NOT NULL;

ALTER TABLE `torrents`
  modify `UserID` int NOT NULL;

ALTER TABLE `torrents_artists`
  modify `UserID` int NOT NULL DEFAULT '0';

ALTER TABLE `torrents_leech_stats`
  modify `Seeders` int NOT NULL DEFAULT '0',
  modify `Leechers` int NOT NULL DEFAULT '0',
  modify `Snatched` int NOT NULL DEFAULT '0',
  modify `Balance` bigint NOT NULL DEFAULT '0';

ALTER TABLE `torrents_votes`
  modify `Ups` int NOT NULL DEFAULT '0',
  modify `Total` int NOT NULL DEFAULT '0';

ALTER TABLE `user_bonus`
  modify `user_id` int NOT NULL;

ALTER TABLE `user_flt`
  modify `user_id` int NOT NULL;

ALTER TABLE `user_has_attr`
  modify `UserID` int NOT NULL;

ALTER TABLE `user_has_invite`
  modify `UserID` int NOT NULL;

ALTER TABLE `user_has_invite_source`
  modify `user_id` int NOT NULL,
  modify `invite_source_id` int NOT NULL;

ALTER TABLE `user_last_access`
  modify `user_id` int NOT NULL;

ALTER TABLE `user_read_blog`
  modify `user_id` int NOT NULL,
  modify `blog_id` int NOT NULL;

ALTER TABLE `user_read_forum`
  modify `user_id` int NOT NULL;

ALTER TABLE `user_read_news`
  modify `user_id` int NOT NULL,
  modify `news_id` int NOT NULL;

ALTER TABLE `user_seedbox`
  modify `user_seedbox_id` int NOT NULL AUTO_INCREMENT,
  modify `user_id` int NOT NULL,
  modify `ipaddr` int NOT NULL;

ALTER TABLE `user_summary`
  modify `user_id` int NOT NULL;

ALTER TABLE `user_torrent_remove`
  modify `user_id` int NOT NULL;

ALTER TABLE `users_collage_subs`
  modify `UserID` int NOT NULL;

ALTER TABLE `users_dupes`
  modify `GroupID` int NOT NULL,
  modify `UserID` int NOT NULL;

ALTER TABLE `users_enable_requests`
  modify `UserID` int NOT NULL,
  modify `CheckedBy` int DEFAULT NULL;

ALTER TABLE `users_history_emails`
  modify `users_history_emails_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `users_info`
  modify `UserID` int NOT NULL,
  modify `StyleID` int NOT NULL,
  modify `RatioWatchDownload` bigint NOT NULL DEFAULT '0',
  modify `RatioWatchTimes` tinyint NOT NULL DEFAULT '0',
  modify `collages` int NOT NULL DEFAULT '0';

ALTER TABLE `users_leech_stats`
  modify `UserID` int NOT NULL,
  modify `Uploaded` bigint NOT NULL DEFAULT '0',
  modify `Downloaded` bigint NOT NULL DEFAULT '0';

ALTER TABLE `users_levels`
  modify `UserID` int NOT NULL,
  modify `PermissionID` int NOT NULL;

ALTER TABLE `users_main`
  modify `ID` int NOT NULL AUTO_INCREMENT,
  modify `Invites` int NOT NULL DEFAULT '0',
  modify `PermissionID` int NOT NULL;

ALTER TABLE `users_stats_daily`
  modify `UserID` int NOT NULL;

ALTER TABLE `users_stats_monthly`
  modify `UserID` int NOT NULL;

ALTER TABLE `users_stats_yearly`
  modify `UserID` int NOT NULL;

ALTER TABLE `users_torrent_history`
  modify `UserID` int NOT NULL,
  modify `NumTorrents` int NOT NULL,
  modify `Date` int NOT NULL,
  modify `Time` int NOT NULL DEFAULT '0',
  modify `LastTime` int NOT NULL DEFAULT '0',
  modify `Weight` bigint NOT NULL DEFAULT '0';

ALTER TABLE `users_votes`
  modify `UserID` int NOT NULL;

ALTER TABLE `users_warnings_forums`
  modify `UserID` int NOT NULL;

ALTER TABLE `wiki_revisions`
  modify `Author` int NOT NULL;

ALTER TABLE `xbt_client_whitelist`
  modify `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `xbt_files_users`
  modify `upspeed` int NOT NULL DEFAULT '0',
  modify `downspeed` int NOT NULL DEFAULT '0',
  modify `timespent` int NOT NULL DEFAULT '0';

ALTER TABLE `xbt_forex`
  modify `btc_forex_id` int NOT NULL AUTO_INCREMENT;
        ");
    }
}
