<?php

use Phinx\Migration\AbstractMigration;

/* This migration is fairly fragile, as it expects very precise names for
 * foreign keys. On a fresh checkout and build of the repository, it can
 * be run and rolled back without error, but on a Gazelle installation that
 * has seen a bit of action, you are likely to encounter trouble.
 *
 * The modifyColumn migrations should be idempotent. The index (primary or
 * otherwise) migrations should also be idempotent. For foreign keys, Mysql
 * does not admit a 'ADD CONSTRAINT IF NOT EXISTS', so the only way, short
 * of delving into the information_schema (which is certainly feasible but
 * much more work) is to execute the ALTER TABLE and see if any smoke comes
 * out. If this happens, use SHOW CREATE TABLE t\G to see what constraint
 * names are already in use and amend the definitions here as appropriate.
 *
 * You should be able to attempt the migration repeatedly until things
 * finally work. The DDL statements are echoed to stdout by design: to help
 * debug any problems that could arise.
 *
 * Some potentially voluminous tables are touched in this migration. Doing
 * this on a live site could cause havoc. Use ptosc or ghost to perform the
 * changes manually and then mark the migration as done in the phinx table.
 * In the up() method call you can wrap the execute() in a conditional to
 * skip a particular modification.
 *
 * Given that this is another hairy migration that may make things blow up,
 * it is assumed that you have ready this comment and set the FKEY_MY_DATABASE
 * environment variable to proceeed. Good luck - Spine
 */

class IndexRefactor extends AbstractMigration {
    protected function modifyColumn(): array {
        return [
            [
                't' => 'api_users',
                'old' => "`Time` timestamp NOT NULL DEFAULT current_timestamp()", /* if rollback */
                'new' => "`Time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()", /* if migrate */
            ],
            [
                't' => 'bookmarks_artists',
                'old' => "`Time` datetime NOT NULL",
                'new' => "`Time` datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'bookmarks_torrents',
                'old' => "`Time` datetime NOT NULL",
                'new' => "`Time` datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'collages',
                'old' => "updated datetime NOT NULL",
                'new' => "updated datetime DEFAULT NULL",
            ],
            [
                't' => 'collages_torrents',
                'old' => "AddedOn datetime NOT NULL",
                'new' => "AddedOn datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'do_not_upload',
                'old' => "Sequence mediumint(9) NOT NULL",
                'new' => "Sequence mediumint(8) NOT NULL",
            ],
            [
                't' => 'forums',
                'old' => "CategoryID tinyint(4) DEFAULT NULL",
                'new' => "CategoryID tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'forums_polls_votes',
                'old' => "Vote tinyint(4) NOT NULL",
                'new' => "Vote tinyint(3) unsigned NOT NULL",
            ],
            [
                't' => 'forums_specific_rules',
                'old' => "ForumID int(6) unsigned DEFAULT NULL",
                'new' => "ForumID int(6) unsigned NOT NULL",
            ],
            [
                't' => 'forums_specific_rules',
                'old' => "ThreadID int(10) unsigned DEFAULT NULL",
                'new' => "ThreadID int(10) unsigned NOT NULL",
            ],
            [
                't' => 'forums_topics',
                'old' => "CreatedTime datetime NOT NULL",
                'new' => "CreatedTime datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'forums_topics',
                'old' => "Ranking tinyint(4) DEFAULT 0",
                'new' => "Ranking tinyint(2) DEFAULT 0",
            ],
            [
                't' => 'forums_topics',
                'old' => "CreatedTime datetime NOT NULL",
                'new' => "CreatedTime datetime DEFAULT current_timestamp()",
            ],
            [
                't' => 'news',
                'old' => "Body text NOT NULL",
                'new' => "Body mediumtext NOT NULL",
            ],
            [
                't' => 'requests',
                'old' => "TimeAdded datetime NOT NULL",
                'new' => "TimeAdded datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'requests',
                'old' => "ReleaseType tinyint(4) DEFAULT NULL",
                'new' => "ReleaseType tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'site_history',
                'old' => "Category  tinyint(4) DEFAULT NULL",
                'new' => "Category  tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'site_history',
                'old' => "SubCategory tinyint(4) DEFAULT NULL",
                'new' => "SubCategory tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_delta',
                'old' => "CategoryID tinyint(4) DEFAULT NULL",
                'new' => "CategoryID tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_delta',
                'old' => "ReleaseType tinyint(4) DEFAULT NULL",
                'new' => "ReleaseType tinyint(4) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_hash',
                'old' => "CategoryID tinyint(4) DEFAULT NULL",
                'new' => "CategoryID tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_hash',
                'old' => "ReleaseType tinyint(4) DEFAULT NULL",
                'new' => "ReleaseType tinyint(4) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_requests',
                'old' => "TimeAdded int(12) unsigned NOT NULL",
                'new' => "TimeAdded int(12) unsigned NOT NULL DEFAULT 0",
            ],
            [
                't' => 'sphinx_requests',
                'old' => "LastVote int(12) unsigned NOT NULL",
                'new' => "LastVote int(12) unsigned NOT NULL DEFAULT 0",
            ],
            [
                't' => 'sphinx_requests',
                'old' => "ReleaseType tinyint(4) DEFAULT NULL",
                'new' => "ReleaseType tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_requests',
                'old' => "TimeFilled int(12) unsigned NOT NULL",
                'new' => "TimeFilled int(12) unsigned DEFAULT NULL",
            ],
            [
                't' => 'sphinx_requests_delta',
                'old' => "ReleaseType tinyint(4) DEFAULT NULL",
                'new' => "ReleaseType tinyint(2) DEFAULT NULL",
            ],
            [
                't' => 'sphinx_t',
                'old' => "remyear smallint(6) NOT NULL",
                'new' => "remyear smallint(6) NOT NULL DEFAULT 0",
            ],
            [
                't' => 'top10_history_torrents',
                'old' => "`Rank` tinyint(4) NOT NULL DEFAULT 0",
                'new' => "`Rank` tinyint(2) NOT NULL DEFAULT 0",
            ],
            [
                't' => 'torrents',
                'old' => "Size bigint(20) NOT NULL",
                'new' => "Size bigint(12) NOT NULL",
            ],
            [
                't' => 'torrents',
                'old' => "Time datetime NOT NULL",
                'new' => "Time datetime DEFAULT NULL",
            ],
            [
                't' => 'torrents',
                'old' => "LastReseedRequest datetime NOT NULL",
                'new' => "LastReseedRequest datetime DEFAULT NULL",
            ],
            [
                't' => 'torrents_group',
                'old' => "CatalogueNumber varchar(80) NOT NULL DEFAULT ''",
                'new' => "CatalogueNumber varchar(80) DEFAULT NULL",
            ],
            [
                't' => 'torrents_group',
                'old' => "RecordLabel varchar(80) NOT NULL DEFAULT ''",
                'new' => "RecordLabel varchar(80) DEFAULT NULL",
            ],
            [
                't' => 'torrents_group',
                'old' => "ReleaseType tinyint(4) DEFAULT 21",
                'new' => "ReleaseType tinyint(2) DEFAULT 21",
            ],
            [
                't' => 'users_donor_ranks',
                'old' => "`Rank` tinyint(4) NOT NULL DEFAULT 0",
                'new' => "`Rank` tinyint(2) NOT NULL DEFAULT 0",
            ],
            [
                't' => 'users_donor_ranks',
                'old' => "Hidden tinyint(4) NOT NULL DEFAULT 0",
                'new' => "Hidden tinyint(2) NOT NULL DEFAULT 0",
            ],
            [
                't' => 'users_donor_ranks',
                'old' => "SpecialRank tinyint(4) DEFAULT 0",
                'new' => "SpecialRank tinyint(2) DEFAULT 0",
            ],
            [
                't' => 'users_donor_ranks',
                'old' => "InvitesReceivedRank tinyint(4) DEFAULT 0",
                'new' => "InvitesReceivedRank tinyint(2) DEFAULT 0",
            ],
            [
                't' => 'users_history_passkeys',
                'old' => "OldPassKey varchar(32) DEFAULT NULL",
                'new' => "OldPassKey varchar(32) NOT NULL",
            ],
            [
                't' => 'users_history_passkeys',
                'old' => "NewPassKey varchar(32) DEFAULT NULL",
                'new' => "NewPassKey varchar(32) NOT NULL",
            ],
            [
                't' => 'users_history_passkeys',
                'old' => "ChangeTime datetime DEFAULT NULL",
                'new' => "ChangeTime datetime NOT NULL DEFAULT current_timestamp()",
            ],
            [
                't' => 'users_history_passkeys',
                'old' => "ChangerIP varchar(15) DEFAULT NULL",
                'new' => "ChangerIP varchar(15) NOT NULL",
            ],
            [
                't' => 'users_info',
                'old' => "RatioWatchTimes tinyint(4) NOT NULL DEFAULT 0",
                'new' => "RatioWatchTimes tinyint(1) unsigned NOT NULL DEFAULT 0",
            ],
            [
                't' => 'users_main',
                'old' => "Class tinyint(4) NOT NULL DEFAULT 5",
                'new' => "Class tinyint(2) NOT NULL DEFAULT 5",
            ],
            [
                't' => 'xbt_files_users',
                'old' => "peer_id binary(20) NOT NULL DEFAULT '00000000000000000000'",
                'new' => "peer_id binary(20) NOT NULL DEFAULT '\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0\\0'",
            ],
        ];
    }

    protected function modifyTable(): array {
        return [
            [
                't' => 'artists_alias',
                'old' => "DROP KEY /* IF EXISTS */ name_idx", /* if rollback */
                'new' => "ADD KEY /* IF NOT EXISTS */ name_idx (Name)",     /* if migrate */
            ],
            [
                't' => 'artists_similar',
                'old' => "ADD KEY /* IF NOT EXISTS */ ArtistID (ArtistID, SimilarID)",
                'new' => "DROP KEY /* IF EXISTS */ ArtistID",
            ],
            [
                't' => 'artists_similar',
                'old' => "DROP KEY /* IF EXISTS */ as_similarid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ as_similarid_idx (SimilarID)",
            ],
            [
                't' => 'collages_torrents',
                'old' => "DROP KEY /* IF EXISTS */ group_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ group_idx (GroupID)",
            ],
            [
                't' => 'contest',
                'old' => "DROP KEY /* IF EXISTS */ dateend_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ dateend_idx (DateEnd)",
            ],
            [
                't' => 'do_not_upload',
                'old' => "DROP KEY /* IF EXISTS */ sequence_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ sequence_idx (Sequence)",
            ],
            [
                't' => 'donations',
                'old' => "ADD COLUMN /* IF NOT EXISTS */ Email varchar(255) NOT NULL",
                'new' => "DROP COLUMN /* IF EXISTS */ Email",
            ],
            [
                't' => 'featured_albums',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (GroupID)",
            ],
            [
                't' => 'forums_specific_rules',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (ForumID, ThreadID)",
            ],
            [
                't' => 'login_attempts',
                'old' => "DROP KEY /* IF EXISTS */ attempts_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ attempts_idx (Attempts)",
            ],
            [
                't' => 'permissions',
                'old' => "DROP KEY /* IF EXISTS */ secondary_name_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ secondary_name_idx (Secondary, Name)",
            ],
            [
                't' => 'pm_conversations_users',
                'old' => "DROP KEY /* IF EXISTS */ pcu_userid_unread_ininbox",
                'new' => "ADD KEY /* IF NOT EXISTS */ pcu_userid_unread_ininbox (UserID, UnRead, InInbox)",
            ],
            [
                't' => 'referral_users',
                'old' => "DROP KEY /* IF EXISTS */ ru_invitekey_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ ru_invitekey_idx (InviteKey)",
            ],

            [
                't' => 'reportsv2',
                'old' => "ADD KEY /* IF NOT EXISTS */ ResolverID (ResolverID)",
                'new' => "DROP KEY /* IF EXISTS */ ResolverID",
            ],
            [
                't' => 'reportsv2',
                'old' => "DROP KEY /* IF EXISTS */ resolver_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ resolver_idx (ResolverID)",
            ],
            [
                't' => 'reportsv2',
                'old' => "DROP KEY /* IF EXISTS */ r2_torrentid_status",
                'new' => "ADD KEY /* IF NOT EXISTS */ r2_torrentid_status (TorrentID, Status)",
            ],
            [
                't' => 'reportsv2',
                'old' => "DROP KEY /* IF EXISTS */ r2_lastchange_resolver_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ r2_lastchange_resolver_idx (LastChangeTime, ResolverID)",
            ],
            [
                't' => 'requests_artists',
                'old' => "DROP KEY /* IF EXISTS */ artistid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ artistid_idx (ArtistID)",
            ],
            [
                't' => 'requests_artists',
                'old' => "DROP KEY /* IF EXISTS */ aliasid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ aliasid_idx (AliasID)",
            ],
            [
                't' => 'staff_blog_visits',
                'old' => "ADD UNIQUE KEY /* IF NOT EXISTS */ UserID (UserID)",
                'new' => "DROP KEY /* IF EXISTS */ UserID",
            ],
            [
                't' => 'staff_pm_conversations',
                'old' => "DROP KEY /* IF EXISTS */ spc_user_unr_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ spc_user_unr_idx (UserID, Unread)",
            ],
            [
                't' => 'staff_pm_messages',
                'old' => "DROP KEY /* IF EXISTS */ convid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ convid_idx (ConvID)",
            ],
            [
                't' => 'stylesheets',
                'old' => "DROP KEY /* IF EXISTS */ default_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ default_idx (`Default`)",
            ],
            [
                't' => 'torrents_bad_files',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'torrents_bad_tags',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'torrents_cassette_approved',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'torrents_lossymaster_approved',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'torrents_lossyweb_approved',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'torrents_missing_lineage',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (TorrentID)",
            ],
            [
                't' => 'users_geodistribution',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (Code)",
            ],
            [
                't' => 'users_history_passkeys',
                'old' => "DROP PRIMARY KEY",
                'new' => "ADD PRIMARY KEY (UserID, OldPassKey)",
            ],
            [
                't' => 'users_info',
                'old' => "DROP KEY /* IF EXISTS */ ui_bandate_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ ui_bandate_idx (BanDate)",
            ],
            [
                't' => 'users_main',
                'old' => "ADD KEY /* IF NOT EXISTS */ PassHash (PassHash)",
                'new' => "DROP KEY /* IF EXISTS */ PassHash",
            ],
            [
                't' => 'users_notify_quoted',
                'old' => "DROP KEY /* IF EXISTS */ page_pageid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ page_pageid_idx (Page,PageID)",
            ],
            [
                't' => 'users_subscriptions',
                'old' => "DROP KEY /* IF EXISTS */ us_topicid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ us_topicid_idx (TopicID)",
            ],
            [
                't' => 'users_subscriptions_comments',
                'old' => "DROP KEY /* IF EXISTS */ usc_pageid_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ usc_pageid_idx (PageID)",
            ],
            [
                't' => 'wiki_aliases',
                'old' => "DROP KEY /* IF EXISTS */ article_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ article_idx (ArticleID)",
            ],
            [
                't' => 'xbt_files_history',
                'old' => "DROP KEY /* IF EXISTS */ xfh_uid_fid_idx",
                'new' => "ADD UNIQUE KEY /* IF NOT EXISTS */ xfh_uid_fid_idx (uid,fid)",
            ],
            [
                't' => 'xbt_files_users',
                'old' => "DROP PRIMARY KEY, ADD PRIMARY KEY (uid, peer_id, fid) ",
                'new' => "DROP PRIMARY KEY, ADD PRIMARY KEY (peer_id, fid, uid) ",
            ],
            [
                't' => 'xbt_files_users',
                'old' => "ADD KEY /* IF NOT EXISTS */ remaining_idx (remaining), ADD KEY /* IF NOT EXISTS */ uid_active (uid, active)",
                'new' => "DROP KEY /* IF EXISTS */ remaining_idx, DROP KEY /* IF EXISTS */ uid_active",
            ],
            [
                't' => 'xbt_files_users',
                'old' => "DROP KEY /* IF EXISTS */ uid_active_remain_mtime_idx, DROP KEY /* IF EXISTS */ remain_mtime_idx",
                'new' => "ADD KEY /* IF NOT EXISTS */ uid_active_remain_mtime_idx (uid, active, remaining, mtime), ADD KEY /* IF NOT EXISTS */ remain_mtime_idx (remaining, mtime)",
            ],
            [
                't' => 'contest',
                'old' => "DROP CONSTRAINT contest_type_fk",
                'new' => "ADD CONSTRAINT contest_type_fk FOREIGN KEY (ContestTypeID) REFERENCES contest_type (ID)",
            ],
            [
                't' => 'contest_leaderboard',
                'old' => "DROP CONSTRAINT contest_leaderboard_fk",
                'new' => "ADD CONSTRAINT contest_leaderboard_fk FOREIGN KEY (ContestID) REFERENCES contest (ID) ON DELETE CASCADE",
            ],
            [
                't' => 'locked_accounts',
                'old' => "DROP CONSTRAINT locked_accounts_fk",
                'new' => "ADD CONSTRAINT locked_accounts_fk FOREIGN KEY (UserID) REFERENCES users_main (ID) ON DELETE CASCADE",
            ],
            [
                't' => 'staff_blog_visits',
                'old' => "DROP CONSTRAINT staff_blog_visits_ibfk_1",
                'new' => "ADD CONSTRAINT staff_blog_visits_ibfk_1 FOREIGN KEY (UserID) REFERENCES users_main (ID) ON DELETE CASCADE",
            ],
            [
                't' => 'torrents_votes',
                'old' => "DROP CONSTRAINT torrents_votes_ibfk_1",
                'new' => "ADD CONSTRAINT torrents_votes_ibfk_1 FOREIGN KEY (GroupID) REFERENCES torrents_group (ID) ON DELETE CASCADE",
            ],
            [
                't' => 'users_dupes',
                'old' => "DROP CONSTRAINT users_dupes_ibfk_1, DROP CONSTRAINT users_dupes_ibfk_2",
                'new' => "ADD CONSTRAINT users_dupes_ibfk_1 FOREIGN KEY (UserID) REFERENCES users_main (ID) ON DELETE CASCADE, ADD CONSTRAINT users_dupes_ibfk_2 FOREIGN KEY (GroupID) REFERENCES dupe_groups (ID) ON DELETE CASCADE",
            ],
            [
                't' => 'users_enable_requests',
                'old' => "DROP CONSTRAINT users_enable_requests_ibfk_1, DROP CONSTRAINT users_enable_requests_ibfk_2",
                'new' => "ADD CONSTRAINT users_enable_requests_ibfk_1 FOREIGN KEY (UserID) REFERENCES users_main (ID), ADD CONSTRAINT users_enable_requests_ibfk_2 FOREIGN KEY (CheckedBy) REFERENCES users_main (ID)",
            ],
            [
                't' => 'users_votes',
                'old' => "DROP CONSTRAINT users_votes_ibfk_1, DROP CONSTRAINT users_votes_ibfk_2",
                'new' => "ADD CONSTRAINT users_votes_ibfk_1 FOREIGN KEY (GroupID) REFERENCES torrents_group (ID) ON DELETE CASCADE, ADD CONSTRAINT `users_votes_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE",
            ],
        ];
    }

    protected function engineChange(): array {
        /* change from MyISAM to InnoDB */
        return [
            'sphinx_delta',
            'sphinx_hash',
            'torrents_files',
            'torrents_peerlists',
            'torrents_peerlists_compare',
        ];
    }

    public function up(): void {
        if (!getenv('FKEY_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }

        foreach ($this->modifyColumn() as $mod) {
            $sql = sprintf("ALTER TABLE %s MODIFY %s", $mod['t'], $mod['new']);
            echo "$sql\n";
            $this->execute($sql);
        }

        foreach ($this->modifyTable() as $mod) {
            $sql = sprintf("ALTER TABLE %s %s", $mod['t'], $mod['new']);
            echo "$sql\n";
            $this->execute($sql);
        }

        foreach ($this->engineChange() as $table) {
            $sql = sprintf("ALTER TABLE %s ENGINE=InnoDB ROW_FORMAT=DYNAMIC", $table);
            echo "$sql\n";
            $this->execute($sql);
        }
    }

    public function down(): void {
        if (!getenv('FKEY_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }

        foreach ($this->modifyColumn() as $mod) {
            $sql = sprintf("ALTER TABLE %s MODIFY %s", $mod['t'], $mod['old']);
            echo "$sql\n";
            $this->execute($sql);
        }

        foreach ($this->modifyTable() as $mod) {
            $sql = sprintf("ALTER TABLE %s %s", $mod['t'], $mod['old']);
            echo "$sql\n";
            $this->execute($sql);
        }

        foreach ($this->engineChange() as $table) {
            $sql = sprintf("ALTER TABLE %s ENGINE=MyISAM", $table);
            echo "$sql\n";
            $this->execute($sql);
        }
    }
}
