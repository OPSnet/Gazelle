<?php

use Phinx\Migration\AbstractMigration;

/**
 * See 20200314171641_migrate_leech_stat_tables.php for a more complete
 * description of why this migration failed.
 *
 * Turn userstats on and find unused indexes with:
 *
SELECT t.table_name, t.index_name, coalesce(i.rows_read, 0) as rows_read
FROM (
    SELECT DISTINCT s.table_schema, s.table_name, s.index_name
    FROM information_schema.statistics s WHERE s.table_schema = ?
) t
LEFT JOIN information_schema.index_statistics i USING (table_schema, table_name, index_name)
ORDER BY t.table_name, rows_read DESC, t.index_name;
 *
 */

class DropUnusedIndexes extends AbstractMigration {
    public function up() {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("ALTER TABLE comments_edits DROP KEY /* IF EXISTS */ EditUser");
        $this->execute("ALTER TABLE collages_artists DROP KEY /* IF EXISTS */ Sort");
        $this->execute("ALTER TABLE collages_torrents DROP KEY /* IF EXISTS */ Sort");
        $this->execute("ALTER TABLE donations DROP KEY /* IF EXISTS */ Amount");
        $this->execute("ALTER TABLE forums DROP KEY /* IF EXISTS */ MinClassRead, DROP KEY /* IF EXISTS */ Sort");
        $this->execute("ALTER TABLE forums_categories DROP KEY /* IF EXISTS */ Sort");
        $this->execute("ALTER TABLE forums_topics DROP KEY /* IF EXISTS */ Title");
        $this->execute("ALTER TABLE forums_topic_notes DROP KEY /* IF EXISTS */ AuthorID");
        $this->execute("ALTER TABLE friends DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE group_log DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE invite_tree DROP KEY /* IF EXISTS */ InviterID");
        $this->execute("ALTER TABLE ip_bans DROP KEY /* IF EXISTS */ ToIP");
        $this->execute("ALTER TABLE irc_channels DROP KEY /* IF EXISTS */ Name");
        $this->execute("ALTER TABLE login_attempts DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE news DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE pm_conversations_users DROP KEY /* IF EXISTS */ ForwardedTo, DROP KEY /* IF EXISTS */ InSentbox, DROP KEY /* IF EXISTS */ ReceivedDate, DROP KEY /* IF EXISTS */ SentDate, DROP KEY /* IF EXISTS */ Sticky");
        $this->execute("ALTER TABLE reports DROP KEY /* IF EXISTS */ ResolvedTime, DROP KEY /* IF EXISTS */ Type");
        $this->execute("ALTER TABLE requests DROP KEY /* IF EXISTS */ LastVote, DROP KEY /* IF EXISTS */ Name, DROP KEY /* IF EXISTS */ TimeFilled, DROP KEY /* IF EXISTS */ Year");
        $this->execute("ALTER TABLE sphinx_a DROP KEY /* IF EXISTS */ gid");
        $this->execute("ALTER TABLE sphinx_requests DROP KEY /* IF EXISTS */ Filled, DROP KEY /* IF EXISTS */ FillerID, DROP KEY /* IF EXISTS */ LastVote, DROP KEY /* IF EXISTS */ Name, DROP KEY /* IF EXISTS */ TimeAdded, DROP KEY /* IF EXISTS */ TimeFilled, DROP KEY /* IF EXISTS */ Userid, DROP KEY /* IF EXISTS */ Year");
        $this->execute("ALTER TABLE sphinx_requests_delta DROP KEY /* IF EXISTS */ Filled, DROP KEY /* IF EXISTS */ FillerID, DROP KEY /* IF EXISTS */ LastVote, DROP KEY /* IF EXISTS */ Name, DROP KEY /* IF EXISTS */ TimeFilled, DROP KEY /* IF EXISTS */ Year");
        $this->execute("ALTER TABLE sphinx_t DROP KEY /* IF EXISTS */ format");
        $this->execute("ALTER TABLE staff_blog DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE tags DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE torrents DROP KEY /* IF EXISTS */ FileCount, DROP KEY /* IF EXISTS */ Size, DROP KEY /* IF EXISTS */ Year");
        $this->execute("ALTER TABLE torrents_cassette_approved DROP KEY /* IF EXISTS */ TimeAdded");
        $this->execute("ALTER TABLE torrents_group DROP KEY /* IF EXISTS */ RevisionID, DROP KEY /* IF EXISTS */ Time");
        $this->execute("ALTER TABLE torrents_leech_stats DROP KEY /* IF EXISTS */ tls_leechers_idx");
        $this->execute("ALTER TABLE torrents_lossymaster_approved DROP KEY /* IF EXISTS */ TimeAdded");
        $this->execute("ALTER TABLE torrents_lossyweb_approved DROP KEY /* IF EXISTS */ TimeAdded");
        $this->execute("ALTER TABLE torrents_peerlists DROP KEY /* IF EXISTS */ GroupID");
        $this->execute("ALTER TABLE torrents_peerlists_compare DROP KEY /* IF EXISTS */ GroupID, DROP KEY /* IF EXISTS */ Stats");
        $this->execute("ALTER TABLE torrents_tags DROP KEY /* IF EXISTS */ PositiveVotes, DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE users_freeleeches DROP KEY /* IF EXISTS */ Time");
        $this->execute("ALTER TABLE users_info DROP KEY /* IF EXISTS */ AuthKey, DROP KEY /* IF EXISTS */ BitcoinAddress, DROP KEY /* IF EXISTS */ DisableInvites, DROP KEY /* IF EXISTS */ RatioWatchDownload");
        $this->execute("ALTER TABLE users_history_ips DROP KEY /* IF EXISTS */ StartTime");
        $this->execute("ALTER TABLE users_leech_stats DROP KEY /* IF EXISTS */ uls_uploaded_idx");
        $this->execute("ALTER TABLE users_main DROP KEY /* IF EXISTS */ Invites, DROP KEY /* IF EXISTS */ IP");
        $this->execute("ALTER TABLE users_notify_filters DROP KEY /* IF EXISTS */ ToYear");
        $this->execute("ALTER TABLE users_sessions DROP KEY /* IF EXISTS */ LastUpdate");
        $this->execute("ALTER TABLE users_torrent_history DROP KEY /* IF EXISTS */ Date, DROP KEY /* IF EXISTS */ Finished");
        $this->execute("ALTER TABLE users_votes DROP KEY /* IF EXISTS */ Time, DROP KEY /* IF EXISTS */ Type");
        $this->execute("ALTER TABLE wiki_artists DROP KEY /* IF EXISTS */ Time, DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE wiki_torrents DROP KEY /* IF EXISTS */ Time, DROP KEY /* IF EXISTS */ UserID");
        $this->execute("ALTER TABLE xbt_snatched DROP KEY /* IF EXISTS */ tstamp");
    }

    public function down() {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->execute("ALTER TABLE comments_edits ADD KEY /* IF NOT EXISTS */ EditUser (EditUser)");
        $this->execute("ALTER TABLE collages_artists ADD KEY /* IF NOT EXISTS */ Sort (Sort)");
        $this->execute("ALTER TABLE collages_torrents ADD KEY /* IF NOT EXISTS */ Sort (Sort)");
        $this->execute("ALTER TABLE donations ADD KEY /* IF NOT EXISTS */ Amount (Amount)");
        $this->execute("ALTER TABLE forums ADD KEY /* IF NOT EXISTS */ MinClassRead (MinClassRead), ADD KEY /* IF NOT EXISTS */ Sort (Sort)");
        $this->execute("ALTER TABLE forums_categories ADD KEY /* IF NOT EXISTS */ Sort (Sort)");
        $this->execute("ALTER TABLE forums_topics ADD KEY /* IF NOT EXISTS */ Title (Title)");
        $this->execute("ALTER TABLE forums_topic_notes ADD KEY /* IF NOT EXISTS */ AuthorID (AuthorID)");
        $this->execute("ALTER TABLE friends ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE group_log ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE invite_tree ADD KEY /* IF NOT EXISTS */ InviterID (InviterID)");
        $this->execute("ALTER TABLE ip_bans ADD KEY /* IF NOT EXISTS */ ToIP (ToIP)");
        $this->execute("ALTER TABLE irc_channels ADD KEY /* IF NOT EXISTS */ Name (Name)");
        $this->execute("ALTER TABLE login_attempts ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE news ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE pm_conversations_users ADD KEY /* IF NOT EXISTS */ ForwardedTo (ForwardedTo), ADD KEY /* IF NOT EXISTS */ InSentbox (InSentbox), ADD KEY /* IF NOT EXISTS */ ReceivedDate (ReceivedDate), ADD KEY /* IF NOT EXISTS */ SentDate (SentDate), ADD KEY /* IF NOT EXISTS */ Sticky (Sticky)");
        $this->execute("ALTER TABLE reports ADD KEY /* IF NOT EXISTS */ ResolvedTime (ResolvedTime), ADD KEY /* IF NOT EXISTS */ Type (Type)");
        $this->execute("ALTER TABLE requests ADD KEY /* IF NOT EXISTS */ LastVote (LastVote), ADD KEY /* IF NOT EXISTS */ Name (Title), ADD KEY /* IF NOT EXISTS */ TimeFilled (TimeFilled), ADD KEY /* IF NOT EXISTS */ Year (Year)");
        $this->execute("ALTER TABLE sphinx_a ADD KEY /* IF NOT EXISTS */ gid (gid)");
        $this->execute("ALTER TABLE sphinx_requests ADD KEY /* IF NOT EXISTS */ Filled (TorrentID), ADD KEY /* IF NOT EXISTS */ FillerID (FillerID), ADD KEY /* IF NOT EXISTS */ LastVote (LastVote), ADD KEY /* IF NOT EXISTS */ Name (Title), ADD KEY /* IF NOT EXISTS */ TimeAdded (TimeAdded), ADD KEY /* IF NOT EXISTS */ TimeFilled (TimeFilled), ADD KEY /* IF NOT EXISTS */ Userid (Userid), ADD KEY /* IF NOT EXISTS */ Year (Year)");
        $this->execute("ALTER TABLE sphinx_requests_delta ADD KEY /* IF NOT EXISTS */ Filled (TorrentID), ADD KEY /* IF NOT EXISTS */ FillerID (FillerID), ADD KEY /* IF NOT EXISTS */ LastVote (LastVote), ADD KEY /* IF NOT EXISTS */ Name (Title), ADD KEY /* IF NOT EXISTS */ TimeFilled (TimeFilled), ADD KEY /* IF NOT EXISTS */ Year (Year)");
        $this->execute("ALTER TABLE sphinx_t ADD KEY /* IF NOT EXISTS */ format (format)");
        $this->execute("ALTER TABLE staff_blog ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE tags ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE torrents ADD KEY /* IF NOT EXISTS */ FileCount (FileCount), ADD KEY /* IF NOT EXISTS */ Size (Size), ADD KEY /* IF NOT EXISTS */ Year (RemasterYear)");
        $this->execute("ALTER TABLE torrents_cassette_approved ADD KEY /* IF NOT EXISTS */ TimeAdded (TimeAdded)");
        $this->execute("ALTER TABLE torrents_group ADD KEY /* IF NOT EXISTS */ RevisionID (RevisionID), ADD KEY /* IF NOT EXISTS */ Time (Time)");
        $this->execute("ALTER TABLE torrents_leech_stats ADD KEY /* IF NOT EXISTS */ tls_leechers_idx (Leechers)");
        $this->execute("ALTER TABLE torrents_lossymaster_approved ADD KEY /* IF NOT EXISTS */ TimeAdded (TimeAdded)");
        $this->execute("ALTER TABLE torrents_lossyweb_approved ADD KEY /* IF NOT EXISTS */ TimeAdded (TimeAdded)");
        $this->execute("ALTER TABLE torrents_peerlists ADD KEY /* IF NOT EXISTS */ GroupID (GroupID)");
        $this->execute("ALTER TABLE torrents_peerlists_compare ADD KEY /* IF NOT EXISTS */ GroupID (GroupID), ADD KEY /* IF NOT EXISTS */ Stats (TorrentID, Seeders, Leechers, Snatches)");
        $this->execute("ALTER TABLE torrents_tags ADD KEY /* IF NOT EXISTS */ PositiveVotes (PositiveVotes), ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE users_freeleeches ADD KEY /* IF NOT EXISTS */ Time (Time)");
        $this->execute("ALTER TABLE users_info ADD KEY /* IF NOT EXISTS */ AuthKey (AuthKey), ADD KEY /* IF NOT EXISTS */ BitcoinAddress (BitcoinAddress), ADD KEY /* IF NOT EXISTS */ DisableInvites (DisableInvites), ADD KEY /* IF NOT EXISTS */ RatioWatchDownload (RatioWatchDownload)");
        $this->execute("ALTER TABLE users_history_ips ADD KEY /* IF NOT EXISTS */ StartTime (StartTime)");
        $this->execute("ALTER TABLE users_leech_stats ADD KEY /* IF NOT EXISTS */ uls_uploaded_idx (Uploaded)");
        $this->execute("ALTER TABLE users_main ADD KEY /* IF NOT EXISTS */ Invites (Invites), ADD KEY /* IF NOT EXISTS */ IP (IP)");
        $this->execute("ALTER TABLE users_notify_filters ADD KEY /* IF NOT EXISTS */ ToYear (ToYear)");
        $this->execute("ALTER TABLE users_sessions ADD KEY /* IF NOT EXISTS */ LastUpdate (LastUpdate)");
        $this->execute("ALTER TABLE users_torrent_history ADD KEY /* IF NOT EXISTS */ Date (Date), ADD KEY /* IF NOT EXISTS */ Finished (Finished)");
        $this->execute("ALTER TABLE users_votes ADD KEY /* IF NOT EXISTS */ Time (Time), ADD KEY /* IF NOT EXISTS */ Type (Type)");
        $this->execute("ALTER TABLE wiki_artists ADD KEY /* IF NOT EXISTS */ Time (Time), ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE wiki_torrents ADD KEY /* IF NOT EXISTS */ Time (Time), ADD KEY /* IF NOT EXISTS */ UserID (UserID)");
        $this->execute("ALTER TABLE xbt_snatched ADD KEY /* IF NOT EXISTS */ tstamp (tstamp)");
    }
}
