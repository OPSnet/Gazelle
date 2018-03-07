<?php

use Phinx\Migration\AbstractMigration;

class Tables extends AbstractMigration {
	public function down() {
		$this->execute("
SET FOREIGN_KEY_CHECKS = 0;
SET @tables = NULL;
SET GROUP_CONCAT_MAX_LEN=32768;

SELECT GROUP_CONCAT('`', table_schema, '`.`', table_name, '`') INTO @tables
FROM   information_schema.tables
WHERE  table_schema = (SELECT DATABASE()) AND table_name <> 'phinxlog';
SELECT IFNULL(@tables, '') INTO @tables;

SET        @tables = CONCAT('DROP TABLE IF EXISTS ', @tables);
PREPARE    stmt FROM @tables;
EXECUTE    stmt;
DEALLOCATE PREPARE stmt;
SET        FOREIGN_KEY_CHECKS = 1;
DROP FUNCTION binomial_ci;");
	}

	/**
	 * TODO: Migrate from gazelle.sql to a proper change() method
	 */
	public function up() {
		$this->execute("SET FOREIGN_KEY_CHECKS = 0;");

		$this->table('api_applications', ['id' => false, 'primary_key' => 'ID'])
			->addColumn('ID', 'integer', ['limit' => 10, 'identity' => true])
			->addColumn('UserID', 'integer', ['limit' => 10])
			->addColumn('Token', 'char', ['limit' => 32])
			->addColumn('Name', 'string', ['limit' => 50])
			->create();

		$this->table('api_users', ['id' => false, 'primary_key' => ['UserID', 'AppID']])
			->addColumn('UserID', 'integer', ['limit' => 10])
			->addColumn('AppID', 'integer', ['limit' => 10])
			->addColumn('Token', 'char', ['limit' => 32])
			->addColumn('State', 'enum', ['values' => ['0', '1', '2'], 'default' => '0'])
			->addColumn('Time', 'timestamp')
			->addColumn('Access', 'text')
			->create();

		$this->table('artists_alias', ['id' => false, 'primary_key' => 'AliasID'])
			->addColumn('AliasID', 'integer', ['limit' => 10, 'identity' => true])
			->addColumn('ArtistID', 'integer', ['limit' => 10])
			->addColumn('Name', 'string', ['limit' => 200, 'null' => true, 'default' => null])
			->addColumn('Redirect', 'integer', ['limit' => 10, 'default' => 0])
			->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
			->addIndex(['ArtistID', 'Name'])
			->create();

		$this->table('artists_group', ['id' => false, 'primary_key' => 'ArtistID'])
			->addColumn('ArtistID', 'integer', ['limit' => 10, 'identity' => true])
			->addColumn('Name', 'string', ['limit' => 200, 'null' => true, 'default' => null])
			->addColumn('RevisionID', 'integer', ['limit' => 12, 'null' => true, 'default' => null])
			->addColumn('VanityHouse', 'boolean')
			->addColumn('LastCommentID', 'integer', ['limit' => 10, 'default' => 0])
			->addIndex(['Name', 'RevisionID'])
			->create();

		$this->table('artists_similar', ['id' => false, 'primary_key' => ['ArtistID', 'SimilarID']])
			->addColumn('ArtistID', 'integer', ['limit' => 10, 'default' => 0])
			->addColumn('SimilarID', 'integer', ['limit' => 12, 'default' => 0])
			->addIndex(['ArtistID', 'SimilarID'])
			->create();

		$this->table('artists_similar_scores', ['id' => false, 'primary_key' => 'SimilarID'])
			->addColumn('SimilarID', 'integer', ['limit' => 12, 'identity' => true])
			->addColumn('Score', 'integer', ['limit' => 10, 'default' => 0])
			->addIndex('Score')
			->create();

		$this->table('artists_similar_votes', ['id' => false, 'primary_key' => ['SimilarID', 'UserID', 'Way']])
			->addColumn('SimilarID', 'integer', ['limit' => 12])
			->addColumn('UserID', 'integer', ['limit' => 10])
			->addColumn('Way', 'enum', ['values' => ['up', 'down'], 'default' => 'up'])
			->create();

		$this->table('artists_tags', ['id' => false, 'primary_key' => ['TagID', 'ArtistID']])
			->addColumn('TagID', 'integer', ['limit' => 10, 'default' => 0])
			->addColumn('ArtistID', 'integer', ['limit' => 10, 'default' => 0])
			->addColumn('PositiveVotes', 'integer', ['limit' => 6, 'default' => 1])
			->addColumn('NegativeVotes', 'integer', ['limit' => 6, 'default' => 1])
			->addColumn('UserID', 'integer', ['limit' => 10, 'default' => null])
			->addIndex(['TagID', 'ArtistID', 'PositiveVotes', 'NegativeVotes', 'UserID'])
			->create();

		$this->table('bad_passwords', ['id' => false, 'primary_key' => 'Password'])
			->addColumn('Password', 'char', ['limit' => 32])
			->create();

		/*
		$this->table('blog', ['id' => false, 'primary_key' => 'ID'])
			->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
			->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
			->addColumn('Title', 'string', ['limit' => 255])
			->addColumn('Body', 'text')
		*/

		$this->execute("
CREATE TABLE `blog` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` text NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ThreadID` int(10) unsigned DEFAULT NULL,
  `Important` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `bookmarks_artists` (
  `UserID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  KEY `UserID` (`UserID`),
  KEY `ArtistID` (`ArtistID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `bookmarks_collages` (
  `UserID` int(10) NOT NULL,
  `CollageID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  KEY `UserID` (`UserID`),
  KEY `CollageID` (`CollageID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `bookmarks_requests` (
  `UserID` int(10) NOT NULL,
  `RequestID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  KEY `UserID` (`UserID`),
  KEY `RequestID` (`RequestID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `bookmarks_torrents` (
  `UserID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  `Sort` int(11) NOT NULL DEFAULT '0',
  UNIQUE KEY `groups_users` (`GroupID`,`UserID`),
  KEY `UserID` (`UserID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `calendar` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) DEFAULT NULL,
  `Body` mediumtext,
  `Category` tinyint(1) DEFAULT NULL,
  `StartDate` datetime DEFAULT NULL,
  `EndDate` datetime DEFAULT NULL,
  `AddedBy` int(10) DEFAULT NULL,
  `Importance` tinyint(1) DEFAULT NULL,
  `Team` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `changelog` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Message` text NOT NULL,
  `Author` varchar(30) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `collages` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL DEFAULT '',
  `Description` text NOT NULL,
  `UserID` int(10) NOT NULL DEFAULT '0',
  `NumTorrents` int(4) NOT NULL DEFAULT '0',
  `Deleted` enum('0','1') DEFAULT '0',
  `Locked` enum('0','1') NOT NULL DEFAULT '0',
  `CategoryID` int(2) NOT NULL DEFAULT '1',
  `TagList` varchar(500) NOT NULL DEFAULT '',
  `MaxGroups` int(10) NOT NULL DEFAULT '0',
  `MaxGroupsPerUser` int(10) NOT NULL DEFAULT '0',
  `Featured` tinyint(4) NOT NULL DEFAULT '0',
  `Subscribers` int(10) DEFAULT '0',
  `updated` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `UserID` (`UserID`),
  KEY `CategoryID` (`CategoryID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `collages_artists` (
  `CollageID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Sort` int(10) NOT NULL DEFAULT '0',
  `AddedOn` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`CollageID`,`ArtistID`),
  KEY `UserID` (`UserID`),
  KEY `Sort` (`Sort`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `collages_torrents` (
  `CollageID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Sort` int(10) NOT NULL DEFAULT '0',
  `AddedOn` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`CollageID`,`GroupID`),
  KEY `UserID` (`UserID`),
  KEY `Sort` (`Sort`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `comments` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Body` mediumtext,
  `EditedUserID` int(10) DEFAULT NULL,
  `EditedTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Page` (`Page`,`PageID`),
  KEY `AuthorID` (`AuthorID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `comments_edits` (
  `Page` enum('forums','artist','collages','requests','torrents') DEFAULT NULL,
  `PostID` int(10) DEFAULT NULL,
  `EditUser` int(10) DEFAULT NULL,
  `EditTime` datetime DEFAULT NULL,
  `Body` mediumtext,
  KEY `EditUser` (`EditUser`),
  KEY `PostHistory` (`Page`,`PostID`,`EditTime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `comments_edits_tmp` (
  `Page` enum('forums','artist','collages','requests','torrents') DEFAULT NULL,
  `PostID` int(10) DEFAULT NULL,
  `EditUser` int(10) DEFAULT NULL,
  `EditTime` datetime DEFAULT NULL,
  `Body` mediumtext,
  KEY `EditUser` (`EditUser`),
  KEY `PostHistory` (`Page`,`PostID`,`EditTime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `concerts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ConcertID` int(10) NOT NULL,
  `TopicID` int(10) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `ConcertID` (`ConcertID`),
  KEY `TopicID` (`TopicID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `contest` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ContestTypeID` int(11) NOT NULL,
  `Name` varchar(80) COLLATE utf8_swedish_ci NOT NULL,
  `Banner` varchar(128) NOT NULL DEFAULT '',
  `DateBegin` datetime NOT NULL,
  `DateEnd` datetime NOT NULL,
  `Display` int(11) NOT NULL DEFAULT 50,
  `MaxTracked` int(11) NOT NULL DEFAULT 500,
  `WikiText` mediumtext,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  CONSTRAINT `contest_type_fk` FOREIGN KEY (`ContestTypeID`) REFERENCES `contest_type` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `contest_leaderboard` (
  `ContestID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `FlacCount` int(11) NOT NULL,
  `LastTorrentID` int(11) NOT NULL,
  `LastTorrentName` varchar(80) COLLATE utf8_swedish_ci NOT NULL,
  `ArtistList` varchar(80) COLLATE utf8_swedish_ci NOT NULL,
  `ArtistNames` varchar(200) COLLATE utf8_swedish_ci NOT NULL,
  `LastUpload` datetime NOT NULL,
  KEY `contest_fk` (`ContestID`),
  KEY `flac_upload_idx` (`FlacCount`,`LastUpload`,`UserID`),
  CONSTRAINT `contest_fk` FOREIGN KEY (`ContestID`) REFERENCES `contest` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `contest_type` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(32) NOT NULL,
  PRIMARY KEY(`ID`),
  UNIQUE(`Name`)
);

CREATE TABLE `cover_art` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `GroupID` int(10) NOT NULL,
  `Image` varchar(255) NOT NULL DEFAULT '',
  `Summary` varchar(100) DEFAULT NULL,
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Time` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `GroupID` (`GroupID`,`Image`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `currency_conversion_rates` (
  `Currency` char(3) NOT NULL,
  `Rate` decimal(9,4) DEFAULT NULL,
  `Time` datetime DEFAULT NULL,
  PRIMARY KEY (`Currency`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `do_not_upload` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Comment` varchar(255) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Sequence` mediumint(8) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `donations` (
  `UserID` int(10) NOT NULL,
  `Amount` decimal(6,2) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Time` datetime NOT NULL,
  `Currency` varchar(5) NOT NULL DEFAULT 'USD',
  `Source` varchar(30) NOT NULL DEFAULT '',
  `Reason` mediumtext NOT NULL,
  `Rank` int(10) DEFAULT '0',
  `AddedBy` int(10) DEFAULT '0',
  `TotalRank` int(10) DEFAULT '0',
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`),
  KEY `Amount` (`Amount`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `donations_bitcoin` (
  `BitcoinAddress` varchar(34) NOT NULL,
  `Amount` decimal(24,8) NOT NULL,
  KEY `BitcoinAddress` (`BitcoinAddress`,`Amount`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `donor_forum_usernames` (
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Prefix` varchar(30) NOT NULL DEFAULT '',
  `Suffix` varchar(30) NOT NULL DEFAULT '',
  `UseComma` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `donor_rewards` (
  `UserID` int(10) NOT NULL DEFAULT '0',
  `IconMouseOverText` varchar(200) NOT NULL DEFAULT '',
  `AvatarMouseOverText` varchar(200) NOT NULL DEFAULT '',
  `CustomIcon` varchar(200) NOT NULL DEFAULT '',
  `SecondAvatar` varchar(200) NOT NULL DEFAULT '',
  `CustomIconLink` varchar(200) NOT NULL DEFAULT '',
  `ProfileInfo1` text NOT NULL,
  `ProfileInfo2` text NOT NULL,
  `ProfileInfo3` text NOT NULL,
  `ProfileInfo4` text NOT NULL,
  `ProfileInfoTitle1` varchar(255) NOT NULL,
  `ProfileInfoTitle2` varchar(255) NOT NULL,
  `ProfileInfoTitle3` varchar(255) NOT NULL,
  `ProfileInfoTitle4` varchar(255) NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `drives` (
  `DriveID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  `Offset` varchar(10) NOT NULL,
  PRIMARY KEY (`DriveID`),
  KEY `Name` (`Name`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `dupe_groups` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Comments` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `email_blacklist` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `UserID` int(10) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Time` datetime NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `featured_albums` (
  `GroupID` int(10) NOT NULL DEFAULT '0',
  `ThreadID` int(10) NOT NULL DEFAULT '0',
  `Title` varchar(35) NOT NULL DEFAULT '',
  `Started` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Ended` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `featured_merch` (
  `ProductID` int(10) NOT NULL DEFAULT '0',
  `Title` varchar(35) NOT NULL DEFAULT '',
  `Image` varchar(255) NOT NULL DEFAULT '',
  `Started` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Ended` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ArtistID` int(10) unsigned DEFAULT '0'
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `CategoryID` tinyint(2) NOT NULL DEFAULT '0',
  `Sort` int(6) unsigned NOT NULL,
  `Name` varchar(40) NOT NULL DEFAULT '',
  `Description` varchar(255) DEFAULT '',
  `MinClassRead` int(4) NOT NULL DEFAULT '0',
  `MinClassWrite` int(4) NOT NULL DEFAULT '0',
  `MinClassCreate` int(4) NOT NULL DEFAULT '0',
  `NumTopics` int(10) NOT NULL DEFAULT '0',
  `NumPosts` int(10) NOT NULL DEFAULT '0',
  `LastPostID` int(10) NOT NULL DEFAULT '0',
  `LastPostAuthorID` int(10) NOT NULL DEFAULT '0',
  `LastPostTopicID` int(10) NOT NULL DEFAULT '0',
  `LastPostTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `AutoLock` enum('0','1') DEFAULT '1',
  `AutoLockWeeks` int(3) unsigned NOT NULL DEFAULT '4',
  PRIMARY KEY (`ID`),
  KEY `Sort` (`Sort`),
  KEY `MinClassRead` (`MinClassRead`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_categories` (
  `ID` tinyint(2) NOT NULL AUTO_INCREMENT,
  `Name` varchar(40) NOT NULL DEFAULT '',
  `Sort` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `Sort` (`Sort`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_last_read_topics` (
  `UserID` int(10) NOT NULL,
  `TopicID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`TopicID`),
  KEY `TopicID` (`TopicID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_polls` (
  `TopicID` int(10) unsigned NOT NULL,
  `Question` varchar(255) NOT NULL,
  `Answers` text NOT NULL,
  `Featured` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Closed` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`TopicID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_polls_votes` (
  `TopicID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `Vote` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`TopicID`,`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_posts` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `TopicID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Body` mediumtext,
  `EditedUserID` int(10) DEFAULT NULL,
  `EditedTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `TopicID` (`TopicID`),
  KEY `AuthorID` (`AuthorID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_specific_rules` (
  `ForumID` int(6) unsigned DEFAULT NULL,
  `ThreadID` int(10) DEFAULT NULL
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_topic_notes` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `TopicID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL,
  `Body` mediumtext,
  PRIMARY KEY (`ID`),
  KEY `TopicID` (`TopicID`),
  KEY `AuthorID` (`AuthorID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `forums_topics` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Title` varchar(150) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `IsLocked` enum('0','1') NOT NULL DEFAULT '0',
  `IsSticky` enum('0','1') NOT NULL DEFAULT '0',
  `ForumID` int(3) NOT NULL,
  `NumPosts` int(10) NOT NULL DEFAULT '0',
  `LastPostID` int(10) NOT NULL,
  `LastPostTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `LastPostAuthorID` int(10) NOT NULL,
  `StickyPostID` int(10) NOT NULL DEFAULT '0',
  `Ranking` tinyint(2) DEFAULT '0',
  `CreatedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ID`),
  KEY `AuthorID` (`AuthorID`),
  KEY `ForumID` (`ForumID`),
  KEY `IsSticky` (`IsSticky`),
  KEY `LastPostID` (`LastPostID`),
  KEY `Title` (`Title`),
  KEY `CreatedTime` (`CreatedTime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `friends` (
  `UserID` int(10) unsigned NOT NULL,
  `FriendID` int(10) unsigned NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`UserID`,`FriendID`),
  KEY `UserID` (`UserID`),
  KEY `FriendID` (`FriendID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `geoip_country` (
  `StartIP` int(11) unsigned NOT NULL,
  `EndIP` int(11) unsigned NOT NULL,
  `Code` varchar(2) NOT NULL,
  PRIMARY KEY (`StartIP`,`EndIP`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `group_log` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Info` mediumtext,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Hidden` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `GroupID` (`GroupID`),
  KEY `TorrentID` (`TorrentID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `invite_tree` (
  `UserID` int(10) NOT NULL DEFAULT '0',
  `InviterID` int(10) NOT NULL DEFAULT '0',
  `TreePosition` int(8) NOT NULL DEFAULT '1',
  `TreeID` int(10) NOT NULL DEFAULT '1',
  `TreeLevel` int(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`),
  KEY `InviterID` (`InviterID`),
  KEY `TreePosition` (`TreePosition`),
  KEY `TreeID` (`TreeID`),
  KEY `TreeLevel` (`TreeLevel`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `invites` (
  `InviterID` int(10) NOT NULL DEFAULT '0',
  `InviteKey` char(32) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Reason` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`InviteKey`),
  KEY `Expires` (`Expires`),
  KEY `InviterID` (`InviterID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `ip_bans` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `FromIP` int(11) unsigned NOT NULL,
  `ToIP` int(11) unsigned NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `FromIP_2` (`FromIP`,`ToIP`),
  KEY `ToIP` (`ToIP`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `label_aliases` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `BadLabel` varchar(100) NOT NULL,
  `AliasLabel` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `BadLabel` (`BadLabel`),
  KEY `AliasLabel` (`AliasLabel`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `last_sent_email` (
  `UserID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `lastfm_users` (
  `ID` int(10) unsigned NOT NULL,
  `Username` varchar(20) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `library_contest` (
  `UserID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `Points` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`,`TorrentID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `locked_accounts` (
  `UserID` int(10) unsigned NOT NULL,
  `Type` tinyint(1) NOT NULL,
  PRIMARY KEY (`UserID`),
  CONSTRAINT `fk_user_id` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `log` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Message` varchar(400) NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `login_attempts` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `IP` varchar(15) NOT NULL,
  `LastAttempt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Attempts` int(10) unsigned NOT NULL,
  `BannedUntil` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Bans` int(10) unsigned NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `IP` (`IP`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `new_info_hashes` (
  `TorrentID` int(11) NOT NULL,
  `InfoHash` binary(20) DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `InfoHash` (`InfoHash`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `news` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` text NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `ocelot_query_times` (
  `buffer` enum('users','torrents','snatches','peers') NOT NULL,
  `starttime` datetime NOT NULL,
  `ocelotinstance` datetime NOT NULL,
  `querylength` int(11) NOT NULL,
  `timespent` int(11) NOT NULL,
  UNIQUE KEY `starttime` (`starttime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `permissions` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Level` int(10) unsigned NOT NULL,
  `Name` varchar(25) NOT NULL,
  `Values` text NOT NULL,
  `DisplayStaff` enum('0','1') NOT NULL DEFAULT '0',
  `PermittedForums` varchar(150) NOT NULL DEFAULT '',
  `Secondary` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Level` (`Level`),
  KEY `DisplayStaff` (`DisplayStaff`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `pm_conversations` (
  `ID` int(12) NOT NULL AUTO_INCREMENT,
  `Subject` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `pm_conversations_users` (
  `UserID` int(10) NOT NULL DEFAULT '0',
  `ConvID` int(12) NOT NULL DEFAULT '0',
  `InInbox` enum('1','0') NOT NULL,
  `InSentbox` enum('1','0') NOT NULL,
  `SentDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ReceivedDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `UnRead` enum('1','0') NOT NULL DEFAULT '1',
  `Sticky` enum('1','0') NOT NULL DEFAULT '0',
  `ForwardedTo` int(12) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`,`ConvID`),
  KEY `InInbox` (`InInbox`),
  KEY `InSentbox` (`InSentbox`),
  KEY `ConvID` (`ConvID`),
  KEY `UserID` (`UserID`),
  KEY `SentDate` (`SentDate`),
  KEY `ReceivedDate` (`ReceivedDate`),
  KEY `Sticky` (`Sticky`),
  KEY `ForwardedTo` (`ForwardedTo`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `pm_messages` (
  `ID` int(12) NOT NULL AUTO_INCREMENT,
  `ConvID` int(12) NOT NULL DEFAULT '0',
  `SentDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `SenderID` int(10) NOT NULL DEFAULT '0',
  `Body` text,
  PRIMARY KEY (`ID`),
  KEY `ConvID` (`ConvID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `push_notifications_usage` (
  `PushService` varchar(10) NOT NULL,
  `TimesUsed` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`PushService`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `reports` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT '0',
  `ThingID` int(10) unsigned NOT NULL DEFAULT '0',
  `Type` varchar(30) DEFAULT NULL,
  `Comment` text,
  `ResolverID` int(10) unsigned NOT NULL DEFAULT '0',
  `Status` enum('New','InProgress','Resolved') DEFAULT 'New',
  `ResolvedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ReportedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Reason` text NOT NULL,
  `ClaimerID` int(10) unsigned NOT NULL DEFAULT '0',
  `Notes` text NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Status` (`Status`),
  KEY `Type` (`Type`),
  KEY `ResolvedTime` (`ResolvedTime`),
  KEY `ResolverID` (`ResolverID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `reports_email_blacklist` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Type` tinyint(4) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Checked` tinyint(4) NOT NULL DEFAULT '0',
  `ResolverID` int(10) DEFAULT '0',
  `Email` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `reportsv2` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ReporterID` int(10) unsigned NOT NULL DEFAULT '0',
  `TorrentID` int(10) unsigned NOT NULL DEFAULT '0',
  `Type` varchar(20) DEFAULT '',
  `UserComment` text,
  `ResolverID` int(10) unsigned NOT NULL DEFAULT '0',
  `Status` enum('New','InProgress','Resolved') DEFAULT 'New',
  `ReportedTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `LastChangeTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ModComment` text,
  `Track` text,
  `Image` text,
  `ExtraID` text,
  `Link` text,
  `LogMessage` text,
  PRIMARY KEY (`ID`),
  KEY `Status` (`Status`),
  KEY `Type` (`Type`(1)),
  KEY `LastChangeTime` (`LastChangeTime`),
  KEY `TorrentID` (`TorrentID`),
  KEY `ResolverID` (`ResolverID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `requests` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL,
  `LastVote` datetime DEFAULT NULL,
  `CategoryID` int(3) NOT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `Description` text NOT NULL,
  `ReleaseType` tinyint(2) DEFAULT NULL,
  `CatalogueNumber` varchar(50) NOT NULL,
  `BitrateList` varchar(255) DEFAULT NULL,
  `FormatList` varchar(255) DEFAULT NULL,
  `MediaList` varchar(255) DEFAULT NULL,
  `LogCue` varchar(20) DEFAULT NULL,
  `FillerID` int(10) unsigned NOT NULL DEFAULT '0',
  `TorrentID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeFilled` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `RecordLabel` varchar(80) DEFAULT NULL,
  `GroupID` int(10) DEFAULT NULL,
  `OCLC` varchar(55) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `Userid` (`UserID`),
  KEY `Name` (`Title`),
  KEY `Filled` (`TorrentID`),
  KEY `FillerID` (`FillerID`),
  KEY `TimeAdded` (`TimeAdded`),
  KEY `Year` (`Year`),
  KEY `TimeFilled` (`TimeFilled`),
  KEY `LastVote` (`LastVote`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `requests_artists` (
  `RequestID` int(10) unsigned NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `AliasID` int(10) NOT NULL,
  `Importance` enum('1','2','3','4','5','6','7') DEFAULT NULL,
  PRIMARY KEY (`RequestID`,`AliasID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `requests_tags` (
  `TagID` int(10) NOT NULL DEFAULT '0',
  `RequestID` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`TagID`,`RequestID`),
  KEY `TagID` (`TagID`),
  KEY `RequestID` (`RequestID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `requests_votes` (
  `RequestID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Bounty` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`RequestID`,`UserID`),
  KEY `RequestID` (`RequestID`),
  KEY `UserID` (`UserID`),
  KEY `Bounty` (`Bounty`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `schedule` (
  `NextHour` int(2) NOT NULL DEFAULT '0',
  `NextDay` int(2) NOT NULL DEFAULT '0',
  `NextBiWeekly` int(2) NOT NULL DEFAULT '0'
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `site_history` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) DEFAULT NULL,
  `Url` varchar(255) NOT NULL DEFAULT '',
  `Category` tinyint(2) DEFAULT NULL,
  `SubCategory` tinyint(2) DEFAULT NULL,
  `Tags` mediumtext,
  `AddedBy` int(10) DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Body` mediumtext,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `site_options` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(64) NOT NULL,
  `Value` tinytext NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `name_index` (`Name`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_a` (
  `gid` int(11) DEFAULT NULL,
  `aname` text,
  KEY `gid` (`gid`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_delta` (
  `ID` int(10) NOT NULL,
  `GroupID` int(11) NOT NULL DEFAULT '0',
  `GroupName` varchar(255) DEFAULT NULL,
  `ArtistName` varchar(2048) DEFAULT NULL,
  `TagList` varchar(728) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `CatalogueNumber` varchar(50) DEFAULT NULL,
  `RecordLabel` varchar(50) DEFAULT NULL,
  `CategoryID` tinyint(2) DEFAULT NULL,
  `Time` int(12) DEFAULT NULL,
  `ReleaseType` tinyint(2) DEFAULT NULL,
  `Size` bigint(20) DEFAULT NULL,
  `Snatched` int(10) DEFAULT NULL,
  `Seeders` int(10) DEFAULT NULL,
  `Leechers` int(10) DEFAULT NULL,
  `LogScore` int(3) DEFAULT NULL,
  `Scene` tinyint(1) NOT NULL DEFAULT '0',
  `VanityHouse` tinyint(1) NOT NULL DEFAULT '0',
  `HasLog` tinyint(1) DEFAULT NULL,
  `HasCue` tinyint(1) DEFAULT NULL,
  `FreeTorrent` tinyint(1) DEFAULT NULL,
  `Media` varchar(255) DEFAULT NULL,
  `Format` varchar(255) DEFAULT NULL,
  `Encoding` varchar(255) DEFAULT NULL,
  `RemasterYear` varchar(50) NOT NULL DEFAULT '',
  `RemasterTitle` varchar(512) DEFAULT NULL,
  `RemasterRecordLabel` varchar(50) DEFAULT NULL,
  `RemasterCatalogueNumber` varchar(50) DEFAULT NULL,
  `FileList` mediumtext,
  `Description` text,
  `VoteScore` float NOT NULL DEFAULT '0',
  `LastChanged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `GroupID` (`GroupID`),
  KEY `Size` (`Size`)
) ENGINE=MyISAM CHARSET utf8;

CREATE TABLE `sphinx_hash` (
  `ID` int(10) NOT NULL,
  `GroupName` varchar(255) DEFAULT NULL,
  `ArtistName` varchar(2048) DEFAULT NULL,
  `TagList` varchar(728) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `CatalogueNumber` varchar(50) DEFAULT NULL,
  `RecordLabel` varchar(50) DEFAULT NULL,
  `CategoryID` tinyint(2) DEFAULT NULL,
  `Time` int(12) DEFAULT NULL,
  `ReleaseType` tinyint(2) DEFAULT NULL,
  `Size` bigint(20) DEFAULT NULL,
  `Snatched` int(10) DEFAULT NULL,
  `Seeders` int(10) DEFAULT NULL,
  `Leechers` int(10) DEFAULT NULL,
  `LogScore` int(3) DEFAULT NULL,
  `Scene` tinyint(1) NOT NULL DEFAULT '0',
  `VanityHouse` tinyint(1) NOT NULL DEFAULT '0',
  `HasLog` tinyint(1) DEFAULT NULL,
  `HasCue` tinyint(1) DEFAULT NULL,
  `FreeTorrent` tinyint(1) DEFAULT NULL,
  `Media` varchar(255) DEFAULT NULL,
  `Format` varchar(255) DEFAULT NULL,
  `Encoding` varchar(255) DEFAULT NULL,
  `RemasterYear` int(4) DEFAULT NULL,
  `RemasterTitle` varchar(512) DEFAULT NULL,
  `RemasterRecordLabel` varchar(50) DEFAULT NULL,
  `RemasterCatalogueNumber` varchar(50) DEFAULT NULL,
  `FileList` mediumtext,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM CHARSET utf8;

CREATE TABLE `sphinx_index_last_pos` (
  `Type` varchar(16) NOT NULL DEFAULT '',
  `ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Type`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_requests` (
  `ID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeAdded` int(12) unsigned NOT NULL,
  `LastVote` int(12) unsigned NOT NULL,
  `CategoryID` int(3) NOT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `ArtistList` varchar(2048) DEFAULT NULL,
  `ReleaseType` tinyint(2) DEFAULT NULL,
  `CatalogueNumber` varchar(50) NOT NULL,
  `BitrateList` varchar(255) DEFAULT NULL,
  `FormatList` varchar(255) DEFAULT NULL,
  `MediaList` varchar(255) DEFAULT NULL,
  `LogCue` varchar(20) DEFAULT NULL,
  `FillerID` int(10) unsigned NOT NULL DEFAULT '0',
  `TorrentID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeFilled` int(12) unsigned NOT NULL,
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `Bounty` bigint(20) unsigned NOT NULL DEFAULT '0',
  `Votes` int(10) unsigned NOT NULL DEFAULT '0',
  `RecordLabel` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Userid` (`UserID`),
  KEY `Name` (`Title`),
  KEY `Filled` (`TorrentID`),
  KEY `FillerID` (`FillerID`),
  KEY `TimeAdded` (`TimeAdded`),
  KEY `Year` (`Year`),
  KEY `TimeFilled` (`TimeFilled`),
  KEY `LastVote` (`LastVote`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_requests_delta` (
  `ID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeAdded` int(12) unsigned DEFAULT NULL,
  `LastVote` int(12) unsigned DEFAULT NULL,
  `CategoryID` tinyint(4) DEFAULT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `TagList` varchar(728) NOT NULL DEFAULT '',
  `Year` int(4) DEFAULT NULL,
  `ArtistList` varchar(2048) DEFAULT NULL,
  `ReleaseType` tinyint(2) DEFAULT NULL,
  `CatalogueNumber` varchar(50) DEFAULT NULL,
  `BitrateList` varchar(255) DEFAULT NULL,
  `FormatList` varchar(255) DEFAULT NULL,
  `MediaList` varchar(255) DEFAULT NULL,
  `LogCue` varchar(20) DEFAULT NULL,
  `FillerID` int(10) unsigned NOT NULL DEFAULT '0',
  `TorrentID` int(10) unsigned NOT NULL DEFAULT '0',
  `TimeFilled` int(12) unsigned DEFAULT NULL,
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `Bounty` bigint(20) unsigned NOT NULL DEFAULT '0',
  `Votes` int(10) unsigned NOT NULL DEFAULT '0',
  `RecordLabel` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Userid` (`UserID`),
  KEY `Name` (`Title`),
  KEY `Filled` (`TorrentID`),
  KEY `FillerID` (`FillerID`),
  KEY `TimeAdded` (`TimeAdded`),
  KEY `Year` (`Year`),
  KEY `TimeFilled` (`TimeFilled`),
  KEY `LastVote` (`LastVote`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_t` (
  `id` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `size` bigint(20) NOT NULL,
  `snatched` int(11) NOT NULL,
  `seeders` int(11) NOT NULL,
  `leechers` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `logscore` smallint(6) NOT NULL,
  `scene` tinyint(4) NOT NULL,
  `haslog` tinyint(4) NOT NULL,
  `hascue` tinyint(4) NOT NULL,
  `freetorrent` tinyint(4) NOT NULL,
  `media` varchar(15) NOT NULL,
  `format` varchar(15) NOT NULL,
  `encoding` varchar(30) NOT NULL,
  `remyear` smallint(6) NOT NULL,
  `remtitle` varchar(80) NOT NULL,
  `remrlabel` varchar(80) NOT NULL,
  `remcnumber` varchar(80) NOT NULL,
  `filelist` mediumtext,
  `remident` int(10) unsigned NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `gid_remident` (`gid`,`remident`),
  KEY `format` (`format`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `sphinx_tg` (
  `id` int(11) NOT NULL,
  `name` varchar(300) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `year` smallint(6) DEFAULT NULL,
  `rlabel` varchar(80) DEFAULT NULL,
  `cnumber` varchar(80) DEFAULT NULL,
  `catid` smallint(6) DEFAULT NULL,
  `reltype` smallint(6) DEFAULT NULL,
  `vanityhouse` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_answers` (
  `QuestionID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Answer` mediumtext,
  `Date` datetime NOT NULL,
  PRIMARY KEY (`QuestionID`,`UserID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_blog` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` text NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_blog_visits` (
  `UserID` int(10) unsigned NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  UNIQUE KEY `UserID` (`UserID`),
  CONSTRAINT `staff_blog_visits_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_ignored_questions` (
  `QuestionID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  PRIMARY KEY (`QuestionID`,`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_pm_conversations` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Subject` text,
  `UserID` int(11) DEFAULT NULL,
  `Status` enum('Open','Unanswered','Resolved') DEFAULT NULL,
  `Level` int(11) DEFAULT NULL,
  `AssignedToUser` int(11) DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Unread` tinyint(1) DEFAULT NULL,
  `ResolverID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `StatusAssigned` (`Status`,`AssignedToUser`),
  KEY `StatusLevel` (`Status`,`Level`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_pm_messages` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) DEFAULT NULL,
  `SentDate` datetime DEFAULT NULL,
  `Message` text,
  `ConvID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `staff_pm_responses` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Message` text,
  `Name` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `styles_backup` (
  `UserID` int(10) NOT NULL DEFAULT '0',
  `StyleID` int(10) DEFAULT NULL,
  `StyleURL` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`UserID`),
  KEY `StyleURL` (`StyleURL`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE stylesheets (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Default` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `tag_aliases` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `BadTag` varchar(30) DEFAULT NULL,
  `AliasTag` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `BadTag` (`BadTag`),
  KEY `AliasTag` (`AliasTag`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `tags` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) DEFAULT NULL,
  `TagType` enum('genre','other') NOT NULL DEFAULT 'other',
  `Uses` int(12) NOT NULL DEFAULT '1',
  `UserID` int(10) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name_2` (`Name`),
  KEY `TagType` (`TagType`),
  KEY `Uses` (`Uses`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `top10_history` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Type` enum('Daily','Weekly') DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `top10_history_torrents` (
  `HistoryID` int(10) NOT NULL DEFAULT '0',
  `Rank` tinyint(2) NOT NULL DEFAULT '0',
  `TorrentID` int(10) NOT NULL DEFAULT '0',
  `TitleString` varchar(150) NOT NULL DEFAULT '',
  `TagString` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `GroupID` int(10) NOT NULL,
  `UserID` int(10) DEFAULT NULL,
  `Media` varchar(20) DEFAULT NULL,
  `Format` varchar(10) DEFAULT NULL,
  `Encoding` varchar(15) DEFAULT NULL,
  `Remastered` enum('0','1') NOT NULL DEFAULT '0',
  `RemasterYear` int(4) DEFAULT NULL,
  `RemasterTitle` varchar(80) NOT NULL DEFAULT '',
  `RemasterCatalogueNumber` varchar(80) NOT NULL DEFAULT '',
  `RemasterRecordLabel` varchar(80) NOT NULL DEFAULT '',
  `Scene` enum('0','1') NOT NULL DEFAULT '0',
  `HasLog` enum('0','1') NOT NULL DEFAULT '0',
  `HasCue` enum('0','1') NOT NULL DEFAULT '0',
  `HasLogDB` enum('0','1') NOT NULL DEFAULT '0',
  `LogScore` int(6) NOT NULL DEFAULT '0',
  `LogChecksum` enum('0','1') NOT NULL DEFAULT '1',
  `info_hash` blob NOT NULL,
  `FileCount` int(6) NOT NULL,
  `FileList` mediumtext NOT NULL,
  `FilePath` varchar(255) NOT NULL DEFAULT '',
  `Size` bigint(12) NOT NULL,
  `Leechers` int(6) NOT NULL DEFAULT '0',
  `Seeders` int(6) NOT NULL DEFAULT '0',
  `last_action` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `FreeTorrent` enum('0','1','2') NOT NULL DEFAULT '0',
  `FreeLeechType` enum('0','1','2','3','4','5','6','7') NOT NULL DEFAULT '0',
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Description` text,
  `Snatched` int(10) unsigned NOT NULL DEFAULT '0',
  `balance` bigint(20) NOT NULL DEFAULT '0',
  `LastReseedRequest` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `TranscodedFrom` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `InfoHash` (`info_hash`(40)),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`),
  KEY `Media` (`Media`),
  KEY `Format` (`Format`),
  KEY `Encoding` (`Encoding`),
  KEY `Year` (`RemasterYear`),
  KEY `FileCount` (`FileCount`),
  KEY `Size` (`Size`),
  KEY `Seeders` (`Seeders`),
  KEY `Leechers` (`Leechers`),
  KEY `Snatched` (`Snatched`),
  KEY `last_action` (`last_action`),
  KEY `Time` (`Time`),
  KEY `FreeTorrent` (`FreeTorrent`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_artists` (
  `GroupID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `AliasID` int(10) NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT '0',
  `Importance` enum('1','2','3','4','5','6','7') NOT NULL DEFAULT '1',
  PRIMARY KEY (`GroupID`,`ArtistID`,`Importance`),
  KEY `ArtistID` (`ArtistID`),
  KEY `AliasID` (`AliasID`),
  KEY `Importance` (`Importance`),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_bad_files` (
  `TorrentID` int(11) NOT NULL DEFAULT '0',
  `UserID` int(11) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_bad_folders` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_bad_tags` (
  `TorrentID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_balance_history` (
  `TorrentID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `balance` bigint(20) NOT NULL,
  `Time` datetime NOT NULL,
  `Last` enum('0','1','2') DEFAULT '0',
  UNIQUE KEY `TorrentID_2` (`TorrentID`,`Time`),
  UNIQUE KEY `TorrentID_3` (`TorrentID`,`balance`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_cassette_approved` (
  `TorrentID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_files` (
  `TorrentID` int(10) NOT NULL,
  `File` mediumblob NOT NULL,
  PRIMARY KEY (`TorrentID`)
) ENGINE=MyISAM CHARSET utf8;

CREATE TABLE `torrents_group` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `ArtistID` int(10) DEFAULT NULL,
  `CategoryID` int(3) DEFAULT NULL,
  `Name` varchar(300) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `CatalogueNumber` varchar(80) NOT NULL DEFAULT '',
  `RecordLabel` varchar(80) NOT NULL DEFAULT '',
  `ReleaseType` tinyint(2) DEFAULT '21',
  `TagList` varchar(500) NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `RevisionID` int(12) DEFAULT NULL,
  `WikiBody` text NOT NULL,
  `WikiImage` varchar(255) NOT NULL,
  `VanityHouse` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `ArtistID` (`ArtistID`),
  KEY `CategoryID` (`CategoryID`),
  KEY `Name` (`Name`(255)),
  KEY `Year` (`Year`),
  KEY `Time` (`Time`),
  KEY `RevisionID` (`RevisionID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_logs` (
  `LogID` int(10) NOT NULL AUTO_INCREMENT,
  `TorrentID` int(10) NOT NULL DEFAULT '0',
  `Log` mediumtext NOT NULL,
  `FileName` varchar(255) NOT NULL DEFAULT '',
  `Details` mediumtext NOT NULL,
  `Score` int(3) NOT NULL,
  `Checksum` enum('0', '1') NOT NULL DEFAULT '1',
  `Adjusted` enum('0', '1') NOT NULL DEFAULT '0',
  `AdjustedScore` int(3) NOT NULL,
  `AdjustedChecksum` enum('0', '1') NOT NULL DEFAULT '0',
  `AdjustedBy` int(10) NOT NULL DEFAULT '0',
  `AdjustmentReason` text,
  `AdjustmentDetails` text,
  PRIMARY KEY(`LogID`),
  KEY `TorrentID` (`TorrentID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_lossymaster_approved` (
	`TorrentID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_lossyweb_approved` (
		`TorrentID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE torrents_missing_lineage (
			`TorrentID` int(10) NOT NULL DEFAULT '0',
  `UserID` int(10) NOT NULL DEFAULT '0',
  `TimeAdded` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  KEY TimeAdded (TimeAdded)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_peerlists` (
		`TorrentID` int(11) NOT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `Seeders` int(11) DEFAULT NULL,
  `Leechers` int(11) DEFAULT NULL,
  `Snatches` int(11) DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `GroupID` (`GroupID`),
  KEY `Stats` (`TorrentID`,`Seeders`,`Leechers`,`Snatches`)
) ENGINE=MyISAM CHARSET utf8;

CREATE TABLE `torrents_peerlists_compare` (
		`TorrentID` int(11) NOT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `Seeders` int(11) DEFAULT NULL,
  `Leechers` int(11) DEFAULT NULL,
  `Snatches` int(11) DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `GroupID` (`GroupID`),
  KEY `Stats` (`TorrentID`,`Seeders`,`Leechers`,`Snatches`)
) ENGINE=MyISAM CHARSET utf8;

CREATE TABLE `torrents_recommended` (
		`GroupID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`GroupID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_tags` (
		`TagID` int(10) NOT NULL DEFAULT '0',
  `GroupID` int(10) NOT NULL DEFAULT '0',
  `PositiveVotes` int(6) NOT NULL DEFAULT '1',
  `NegativeVotes` int(6) NOT NULL DEFAULT '1',
  `UserID` int(10) DEFAULT NULL,
  PRIMARY KEY (`TagID`,`GroupID`),
  KEY `TagID` (`TagID`),
  KEY `GroupID` (`GroupID`),
  KEY `PositiveVotes` (`PositiveVotes`),
  KEY `NegativeVotes` (`NegativeVotes`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_tags_votes` (
		`GroupID` int(10) NOT NULL,
  `TagID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Way` enum('up','down') NOT NULL DEFAULT 'up',
  PRIMARY KEY (`GroupID`,`TagID`,`UserID`,`Way`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `torrents_votes` (
		`GroupID` int(10) NOT NULL,
  `Ups` int(10) unsigned NOT NULL DEFAULT '0',
  `Total` int(10) unsigned NOT NULL DEFAULT '0',
  `Score` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`GroupID`),
  KEY `Score` (`Score`),
  CONSTRAINT `torrents_votes_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `torrents_group` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `upload_contest` (
		`TorrentID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `upload_contest_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `user_questions` (
		`ID` int(10) NOT NULL AUTO_INCREMENT,
  `Question` mediumtext NOT NULL,
  `UserID` int(10) NOT NULL,
  `Date` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Date` (`Date`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_collage_subs` (
		`UserID` int(10) NOT NULL,
  `CollageID` int(10) NOT NULL,
  `LastVisit` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`,`CollageID`),
  KEY `CollageID` (`CollageID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_comments_last_read` (
		`UserID` int(10) NOT NULL,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`Page`,`PageID`),
  KEY `Page` (`Page`,`PageID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_donor_ranks` (
		`UserID` int(10) NOT NULL DEFAULT '0',
  `Rank` tinyint(2) NOT NULL DEFAULT '0',
  `DonationTime` datetime DEFAULT NULL,
  `Hidden` tinyint(2) NOT NULL DEFAULT '0',
  `TotalRank` int(10) NOT NULL DEFAULT '0',
  `SpecialRank` tinyint(2) DEFAULT '0',
  `InvitesRecievedRank` tinyint(4) DEFAULT '0',
  `RankExpirationTime` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`),
  KEY `DonationTime` (`DonationTime`),
  KEY `SpecialRank` (`SpecialRank`),
  KEY `Rank` (`Rank`),
  KEY `TotalRank` (`TotalRank`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_downloads` (
		`UserID` int(10) NOT NULL,
  `TorrentID` int(1) NOT NULL,
  `Time` datetime NOT NULL,
  PRIMARY KEY (`UserID`,`TorrentID`,`Time`),
  KEY `TorrentID` (`TorrentID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_dupes` (
		`GroupID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  UNIQUE KEY `UserID` (`UserID`),
  KEY `GroupID` (`GroupID`),
  CONSTRAINT `users_dupes_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `users_dupes_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `dupe_groups` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_enable_recommendations` (
		`ID` int(10) NOT NULL,
  `Enable` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Enable` (`Enable`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_enable_requests` (
		`ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Email` varchar(255) NOT NULL,
  `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `UserAgent` text NOT NULL,
  `Timestamp` datetime NOT NULL,
  `HandledTimestamp` datetime DEFAULT NULL,
  `Token` char(32) DEFAULT NULL,
  `CheckedBy` int(10) unsigned DEFAULT NULL,
  `Outcome` tinyint(1) DEFAULT NULL COMMENT '1 for approved, 2 for denied, 3 for discarded',
  PRIMARY KEY (`ID`),
  KEY `UserId` (`UserID`),
  KEY `CheckedBy` (`CheckedBy`),
  CONSTRAINT `users_enable_requests_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`),
  CONSTRAINT `users_enable_requests_ibfk_2` FOREIGN KEY (`CheckedBy`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_freeleeches` (
		`UserID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  `Expired` tinyint(1) NOT NULL DEFAULT '0',
  `Downloaded` bigint(20) NOT NULL DEFAULT '0',
  `Uses` int(10) NOT NULL DEFAULT '1',
  PRIMARY KEY (`UserID`,`TorrentID`),
  KEY `Time` (`Time`),
  KEY `Expired_Time` (`Expired`,`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_geodistribution` (
		`Code` varchar(2) NOT NULL,
  `Users` int(10) NOT NULL
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_history_emails` (
		`UserID` int(10) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Time` datetime DEFAULT NULL,
  `IP` varchar(15) DEFAULT NULL,
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_history_ips` (
		`UserID` int(10) NOT NULL,
  `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `StartTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `EndTime` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`,`IP`,`StartTime`),
  KEY `UserID` (`UserID`),
  KEY `IP` (`IP`),
  KEY `StartTime` (`StartTime`),
  KEY `EndTime` (`EndTime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_history_passkeys` (
		`UserID` int(10) NOT NULL,
  `OldPassKey` varchar(32) DEFAULT NULL,
  `NewPassKey` varchar(32) DEFAULT NULL,
  `ChangeTime` datetime DEFAULT NULL,
  `ChangerIP` varchar(15) DEFAULT NULL
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_history_passwords` (
		`UserID` int(10) NOT NULL,
  `ChangeTime` datetime DEFAULT NULL,
  `ChangerIP` varchar(15) DEFAULT NULL,
  KEY `User_Time` (`UserID`,`ChangeTime`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_info` (
		`UserID` int(10) unsigned NOT NULL,
  `StyleID` int(10) unsigned NOT NULL,
  `StyleURL` varchar(255) DEFAULT NULL,
  `Info` text NOT NULL,
  `Avatar` varchar(255) NOT NULL,
  `AdminComment` text NOT NULL,
  `SiteOptions` text NOT NULL,
  `ViewAvatars` enum('0','1') NOT NULL DEFAULT '1',
  `Donor` enum('0','1') NOT NULL DEFAULT '0',
  `Artist` enum('0','1') NOT NULL DEFAULT '0',
  `DownloadAlt` enum('0','1') NOT NULL DEFAULT '0',
  `Warned` datetime NOT NULL,
  `SupportFor` varchar(255) NOT NULL,
  `TorrentGrouping` enum('0','1','2') NOT NULL COMMENT '0=Open,1=Closed,2=Off',
  `ShowTags` enum('0','1') NOT NULL DEFAULT '1',
  `NotifyOnQuote` enum('0','1','2') NOT NULL DEFAULT '0',
  `AuthKey` varchar(32) NOT NULL,
  `ResetKey` varchar(32) NOT NULL,
  `ResetExpires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `JoinDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Inviter` int(10) DEFAULT NULL,
  `BitcoinAddress` varchar(34) DEFAULT NULL,
  `WarnedTimes` int(2) NOT NULL DEFAULT '0',
  `DisableAvatar` enum('0','1') NOT NULL DEFAULT '0',
  `DisableInvites` enum('0','1') NOT NULL DEFAULT '0',
  `DisablePosting` enum('0','1') NOT NULL DEFAULT '0',
  `DisableForums` enum('0','1') NOT NULL DEFAULT '0',
  `DisablePoints` enum('0','1') NOT NULL DEFAULT '0',
  `DisableIRC` enum('0','1') DEFAULT '0',
  `DisableTagging` enum('0','1') NOT NULL DEFAULT '0',
  `DisableUpload` enum('0','1') NOT NULL DEFAULT '0',
  `DisableWiki` enum('0','1') NOT NULL DEFAULT '0',
  `DisablePM` enum('0','1') NOT NULL DEFAULT '0',
  `RatioWatchEnds` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `RatioWatchDownload` bigint(20) unsigned NOT NULL DEFAULT '0',
  `RatioWatchTimes` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `BanDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `BanReason` enum('0','1','2','3','4') NOT NULL DEFAULT '0',
  `CatchupTime` datetime DEFAULT NULL,
  `LastReadNews` int(10) NOT NULL DEFAULT '0',
  `HideCountryChanges` enum('0','1') NOT NULL DEFAULT '0',
  `RestrictedForums` varchar(150) NOT NULL DEFAULT '',
  `DisableRequests` enum('0','1') NOT NULL DEFAULT '0',
  `PermittedForums` varchar(150) NOT NULL DEFAULT '',
  `UnseededAlerts` enum('0','1') NOT NULL DEFAULT '0',
  `LastReadBlog` int(10) NOT NULL DEFAULT '0',
  `InfoTitle` varchar(255) NOT NULL,
  UNIQUE KEY `UserID` (`UserID`),
  KEY `SupportFor` (`SupportFor`),
  KEY `DisableInvites` (`DisableInvites`),
  KEY `Donor` (`Donor`),
  KEY `Warned` (`Warned`),
  KEY `JoinDate` (`JoinDate`),
  KEY `Inviter` (`Inviter`),
  KEY `RatioWatchEnds` (`RatioWatchEnds`),
  KEY `RatioWatchDownload` (`RatioWatchDownload`),
  KEY `BitcoinAddress` (`BitcoinAddress`(4)),
  KEY `AuthKey` (`AuthKey`),
  KEY `ResetKey` (`ResetKey`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_levels` (
		`UserID` int(10) unsigned NOT NULL,
  `PermissionID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`UserID`,`PermissionID`),
  KEY `PermissionID` (`PermissionID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_main` (
		`ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Username` varchar(20) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `PassHash` varchar(60) NOT NULL,
  `Secret` char(32) NOT NULL,
  `IRCKey` char(32) DEFAULT NULL,
  `LastLogin` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `LastAccess` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `Class` tinyint(2) NOT NULL DEFAULT '5',
  `Uploaded` bigint(20) unsigned NOT NULL DEFAULT '0',
  `Downloaded` bigint(20) unsigned NOT NULL DEFAULT '0',
  `BonusPoints` float(20, 5) NOT NULL DEFAULT '0',
  `Title` text NOT NULL,
  `Enabled` enum('0','1','2') NOT NULL DEFAULT '0',
  `Paranoia` text,
  `Visible` enum('1','0') NOT NULL DEFAULT '1',
  `Invites` int(10) unsigned NOT NULL DEFAULT '0',
  `PermissionID` int(10) unsigned NOT NULL,
  `CustomPermissions` text,
  `can_leech` tinyint(4) NOT NULL DEFAULT '1',
  `torrent_pass` char(32) NOT NULL,
  `RequiredRatio` double(10,8) NOT NULL DEFAULT '0.00000000',
  `RequiredRatioWork` double(10,8) NOT NULL DEFAULT '0.00000000',
  `ipcc` varchar(2) NOT NULL DEFAULT '',
  `FLTokens` int(10) NOT NULL DEFAULT '0',
  `FLT_Given` int(10) NOT NULL DEFAULT '0',
  `Invites_Given` int(10) NOT NULL DEFAULT '0',
  `2FA_Key` VARCHAR(16),
  `Recovery` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Username` (`Username`),
  KEY `Email` (`Email`),
  KEY `PassHash` (`PassHash`),
  KEY `LastAccess` (`LastAccess`),
  KEY `IP` (`IP`),
  KEY `Class` (`Class`),
  KEY `Uploaded` (`Uploaded`),
  KEY `Downloaded` (`Downloaded`),
  KEY `Enabled` (`Enabled`),
  KEY `Invites` (`Invites`),
  KEY `torrent_pass` (`torrent_pass`),
  KEY `RequiredRatio` (`RequiredRatio`),
  KEY `cc_index` (`ipcc`),
  KEY `PermissionID` (`PermissionID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_notifications_settings` (
		`UserID` int(10) NOT NULL DEFAULT '0',
  `Inbox` tinyint(1) DEFAULT '1',
  `StaffPM` tinyint(1) DEFAULT '1',
  `News` tinyint(1) DEFAULT '1',
  `Blog` tinyint(1) DEFAULT '1',
  `Torrents` tinyint(1) DEFAULT '1',
  `Collages` tinyint(1) DEFAULT '1',
  `Quotes` tinyint(1) DEFAULT '1',
  `Subscriptions` tinyint(1) DEFAULT '1',
  `SiteAlerts` tinyint(1) DEFAULT '1',
  `RequestAlerts` tinyint(1) DEFAULT '1',
  `CollageAlerts` tinyint(1) DEFAULT '1',
  `TorrentAlerts` tinyint(1) DEFAULT '1',
  `ForumAlerts` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_notify_filters` (
		`ID` int(12) NOT NULL AUTO_INCREMENT,
  `UserID` int(10) NOT NULL,
  `Label` varchar(128) NOT NULL DEFAULT '',
  `Artists` mediumtext NOT NULL,
  `RecordLabels` mediumtext NOT NULL,
  `Users` mediumtext NOT NULL,
  `Tags` varchar(500) NOT NULL DEFAULT '',
  `NotTags` varchar(500) NOT NULL DEFAULT '',
  `Categories` varchar(500) NOT NULL DEFAULT '',
  `Formats` varchar(500) NOT NULL DEFAULT '',
  `Encodings` varchar(500) NOT NULL DEFAULT '',
  `Media` varchar(500) NOT NULL DEFAULT '',
  `FromYear` int(4) NOT NULL DEFAULT '0',
  `ToYear` int(4) NOT NULL DEFAULT '0',
  `ExcludeVA` enum('1','0') NOT NULL DEFAULT '0',
  `NewGroupsOnly` enum('1','0') NOT NULL DEFAULT '0',
  `ReleaseTypes` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `FromYear` (`FromYear`),
  KEY `ToYear` (`ToYear`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_notify_quoted` (
		`UserID` int(10) NOT NULL,
  `QuoterID` int(10) NOT NULL,
  `Page` enum('forums','artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  `UnRead` tinyint(1) NOT NULL DEFAULT '1',
  `Date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`UserID`,`Page`,`PostID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_notify_torrents` (
		`UserID` int(10) NOT NULL,
  `FilterID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UnRead` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`UserID`,`TorrentID`),
  KEY `TorrentID` (`TorrentID`),
  KEY `UserID_Unread` (`UserID`,`UnRead`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_points` (
		`UserID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `Points` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`UserID`,`GroupID`),
  KEY `UserID` (`UserID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_points_requests` (
		`UserID` int(10) NOT NULL,
  `RequestID` int(10) NOT NULL,
  `Points` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`RequestID`),
  KEY `UserID` (`UserID`),
  KEY `RequestID` (`RequestID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_push_notifications` (
		`UserID` int(10) NOT NULL,
  `PushService` tinyint(1) NOT NULL DEFAULT '0',
  `PushOptions` text NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_sessions` (
		`UserID` int(10) NOT NULL,
  `SessionID` char(32) NOT NULL,
  `KeepLogged` enum('0','1') NOT NULL DEFAULT '0',
  `Browser` varchar(40) DEFAULT NULL,
  `OperatingSystem` varchar(13) DEFAULT NULL,
  `IP` varchar(15) NOT NULL,
  `LastUpdate` datetime NOT NULL,
  `Active` tinyint(4) NOT NULL DEFAULT '1',
  `FullUA` text,
  PRIMARY KEY (`UserID`,`SessionID`),
  KEY `UserID` (`UserID`),
  KEY `LastUpdate` (`LastUpdate`),
  KEY `Active` (`Active`),
  KEY `ActiveAgeKeep` (`Active`,`LastUpdate`,`KeepLogged`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_subscriptions` (
		`UserID` int(10) NOT NULL,
  `TopicID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`TopicID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_subscriptions_comments` (
		`UserID` int(10) NOT NULL,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`Page`,`PageID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_torrent_history` (
		`UserID` int(10) unsigned NOT NULL,
  `NumTorrents` int(6) unsigned NOT NULL,
  `Date` int(8) unsigned NOT NULL,
  `Time` int(11) unsigned NOT NULL DEFAULT '0',
  `LastTime` int(11) unsigned NOT NULL DEFAULT '0',
  `Finished` enum('1','0') NOT NULL DEFAULT '1',
  `Weight` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`,`NumTorrents`,`Date`),
  KEY `Finished` (`Finished`),
  KEY `Date` (`Date`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_torrent_history_snatch` (
		`UserID` int(10) unsigned NOT NULL,
  `NumSnatches` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`),
  KEY `NumSnatches` (`NumSnatches`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_torrent_history_temp` (
		`UserID` int(10) unsigned NOT NULL,
  `NumTorrents` int(6) unsigned NOT NULL DEFAULT '0',
  `SumTime` bigint(20) unsigned NOT NULL DEFAULT '0',
  `SeedingAvg` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_votes` (
		`UserID` int(10) unsigned NOT NULL,
  `GroupID` int(10) NOT NULL,
  `Type` enum('Up','Down') DEFAULT NULL,
  `Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`UserID`,`GroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `Type` (`Type`),
  KEY `Time` (`Time`),
  KEY `Vote` (`Type`,`GroupID`,`UserID`),
  CONSTRAINT `users_votes_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `torrents_group` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `users_votes_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `users_warnings_forums` (
		`UserID` int(10) unsigned NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `wiki_aliases` (
  `Alias` varchar(50) NOT NULL,
  `UserID` int(10) NOT NULL,
  `ArticleID` int(10) DEFAULT NULL,
  PRIMARY KEY (`Alias`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `wiki_articles` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Revision` int(10) NOT NULL DEFAULT '1',
  `Title` varchar(100) DEFAULT NULL,
  `Body` mediumtext,
  `MinClassRead` int(4) DEFAULT NULL,
  `MinClassEdit` int(4) DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Author` int(10) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `wiki_artists` (
		`RevisionID` int(12) NOT NULL AUTO_INCREMENT,
  `PageID` int(10) NOT NULL DEFAULT '0',
  `Body` text,
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Summary` varchar(100) DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`RevisionID`),
  KEY `PageID` (`PageID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `wiki_revisions` (
		`ID` int(10) NOT NULL,
  `Revision` int(10) NOT NULL,
  `Title` varchar(100) DEFAULT NULL,
  `Body` mediumtext,
  `Date` datetime DEFAULT NULL,
  `Author` int(10) DEFAULT NULL,
  KEY `ID_Revision` (`ID`,`Revision`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `wiki_torrents` (
		`RevisionID` int(12) NOT NULL AUTO_INCREMENT,
  `PageID` int(10) NOT NULL DEFAULT '0',
  `Body` text,
  `UserID` int(10) NOT NULL DEFAULT '0',
  `Summary` varchar(100) DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`RevisionID`),
  KEY `PageID` (`PageID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `xbt_client_whitelist` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `peer_id` varchar(20) DEFAULT NULL,
  `vstring` varchar(200) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `peer_id` (`peer_id`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `xbt_files_history` (
		`uid` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `seedtime` int(11) NOT NULL DEFAULT '0',
  `downloaded` bigint(20) NOT NULL DEFAULT '0',
  `uploaded` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `xbt_files_users` (
		`uid` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `announced` int(11) NOT NULL DEFAULT '0',
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `downloaded` bigint(20) NOT NULL DEFAULT '0',
  `remaining` bigint(20) NOT NULL DEFAULT '0',
  `uploaded` bigint(20) NOT NULL DEFAULT '0',
  `upspeed` int(10) unsigned NOT NULL DEFAULT '0',
  `downspeed` int(10) unsigned NOT NULL DEFAULT '0',
  `corrupt` bigint(20) NOT NULL DEFAULT '0',
  `timespent` int(10) unsigned NOT NULL DEFAULT '0',
  `useragent` varchar(51) NOT NULL DEFAULT '',
  `connectable` tinyint(4) NOT NULL DEFAULT '1',
  `peer_id` binary(20) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `fid` int(11) NOT NULL,
  `mtime` int(11) NOT NULL DEFAULT '0',
  `ip` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`peer_id`,`fid`,`uid`),
  KEY `remaining_idx` (`remaining`),
  KEY `fid_idx` (`fid`),
  KEY `mtime_idx` (`mtime`),
  KEY `uid_active` (`uid`,`active`)
) ENGINE=InnoDB CHARSET utf8;

CREATE TABLE `xbt_snatched` (
		`uid` int(11) NOT NULL DEFAULT '0',
  `tstamp` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `IP` varchar(15) NOT NULL,
  `seedtime` int(11) NOT NULL DEFAULT '0',
  KEY `fid` (`fid`),
  KEY `tstamp` (`tstamp`),
  KEY `uid_tstamp` (`uid`,`tstamp`)
) ENGINE=InnoDB CHARSET utf8;

CREATE DEFINER=`root`@`localhost` FUNCTION `binomial_ci`(p int, n int) RETURNS float
    DETERMINISTIC
    SQL SECURITY INVOKER
RETURN IF(n = 0,0.0,((p + 1.35336) / n - 1.6452 * SQRT((p * (n-p)) / n + 0.67668) / n) / (1 + 2.7067 / n));");

		$this->insert('contest_type', [['Name' => 'upload_flac'], ['Name' => 'request_fill']]);

		$this->insert('forums_categories', [
			['ID' => 1, 'Name' => 'Site', 'Sort' => 1],
			['ID' => 21, 'Name' => 'Suggestions', 'Sort' => 3],
			['ID' => 5, 'Name' => 'Community', 'Sort' => 5],
			['ID' => 8, 'Name' => 'Music', 'Sort' => 8],
			['ID' => 10, 'Name' => 'Help', 'Sort' => 10],
			['ID' => 20, 'Name' => 'Trash', 'Sort' => 20]]);

		$this->insert('forums', [
			['ID' => 7, 'CategoryID' => 1, 'Sort' => 100, 'Name' => 'Pharmacy', 'Description' => 'Get your medication dispensed here', 'MinClassRead' => 1000, 'MinClassWrite' => 1000, 'MinClassCreate' => 1000],
			['ID' => 5, 'CategoryID' => 1, 'Sort' => 200, 'Name' => 'Staff', 'Description' => 'No place like home', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 35, 'CategoryID' => 1, 'Sort' => 250, 'Name' => 'Developers', 'Description' => 'Developers forum', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 33, 'CategoryID' => 1, 'Sort' => 750, 'Name' => 'Designers', 'Description' => 'Designers', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 28, 'CategoryID' => 1, 'Sort' => 800, 'Name' => 'First Line Support', 'Description' => 'Special Support Operations Command (SSOC)', 'MinClassRead' => 900, 'MinClassWrite' => 900, 'MinClassCreate' => 900],
			['ID' => 30, 'CategoryID' => 1, 'Sort' => 900, 'Name' => 'Interviewers', 'Description' => 'The Candidates', 'MinClassRead' => 900, 'MinClassWrite' => 900, 'MinClassCreate' => 900],

			['ID' => 31, 'CategoryID' => 1, 'Sort' => 1000, 'Name' => 'Charlie Team', 'Description' => 'Quality Assurance', 'MinClassRead' => 850, 'MinClassWrite' => 850, 'MinClassCreate' => 850],
			['ID' => 1, 'CategoryID' => 1, 'Sort' => 300, 'Name' => 'APOLLO', 'Description' => 'apollo.rip', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 12, 'CategoryID' => 1, 'Sort' => 600, 'Name' => 'Announcements', 'Description' => 'Public service announcements', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 800],
			['ID' => 6, 'CategoryID' => 1, 'Sort' => 400, 'Name' => 'Bugs', 'Description' => 'I found a non critical bug', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 24, 'CategoryID' => 5, 'Sort' => 2000, 'Name' => 'Projects', 'Description' => 'I\'m working on a project', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

			['ID' => 13, 'CategoryID' => 21, 'Sort' => 2990, 'Name' => 'Suggestions', 'Description' => 'I have an idea', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 36, 'CategoryID' => 21, 'Sort' => 3000, 'Name' => 'Approved', 'Description' => 'Self explanatory...', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 800],
			['ID' => 37, 'CategoryID' => 21, 'Sort' => 3400, 'Name' => 'Implemented', 'Description' => 'The Suggestion I made has been implemented', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 15, 'CategoryID' => 21, 'Sort' => 3500, 'Name' => 'Denied', 'Description' => 'The Suggestion I made has been denied', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],

			['ID' => 2, 'CategoryID' => 5, 'Sort' => 1200, 'Name' => 'Chat', 'Description' => 'General chat', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 25, 'CategoryID' => 5, 'Sort' => 2100, 'Name' => 'Games', 'Description' => 'I\'m a gamer', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 27, 'CategoryID' => 5, 'Sort' => 1100, 'Name' => 'Serious Discussions', 'Description' => 'The Library', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 29, 'CategoryID' => 5, 'Sort' => 1300, 'Name' => 'Power User', 'Description' => 'PU Forum <3', 'MinClassRead' => 200, 'MinClassWrite' => 200, 'MinClassCreate' => 200],
			['ID' => 11, 'CategoryID' => 5, 'Sort' => 1600, 'Name' => 'Elites', 'Description' => 'I\'m 1337', 'MinClassRead' => 250, 'MinClassWrite' => 250, 'MinClassCreate' => 250],
			['ID' => 40, 'CategoryID' => 5, 'Sort' => 1610, 'Name' => 'Torrent Masters', 'Description' => 'The Holy Grail', 'MinClassRead' => 400, 'MinClassWrite' => 400, 'MinClassCreate' => 400],
			['ID' => 38, 'CategoryID' => 5, 'Sort' => 1650, 'Name' => 'VIP', 'Description' => 'Very Important Phorum', 'MinClassRead' => 601, 'MinClassWrite' => 601, 'MinClassCreate' => 601],
			['ID' => 10, 'CategoryID' => 5, 'Sort' => 1500, 'Name' => 'Donors', 'Description' => 'I have a heart', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 39, 'CategoryID' => 5, 'Sort' => 1670, 'Name' => 'Invitations', 'Description' => 'Stairway to Heaven', 'MinClassRead' => 250, 'MinClassWrite' => 250, 'MinClassCreate' => 250],
			['ID' => 22, 'CategoryID' => 5, 'Sort' => 1800, 'Name' => 'Comics', 'Description' => 'I read comics', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 23, 'CategoryID' => 5, 'Sort' => 1900, 'Name' => 'Technology', 'Description' => 'I like technology', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

			['ID' => 8, 'CategoryID' => 8, 'Sort' => 30, 'Name' => 'Music', 'Description' => 'For the masses', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 18, 'CategoryID' => 8, 'Sort' => 31, 'Name' => 'Vanity House', 'Description' => 'I have some of my work to share', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 32, 'CategoryID' => 8, 'Sort' => 20, 'Name' => 'Audiophile', 'Description' => 'For the audiophile', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 19, 'CategoryID' => 8, 'Sort' => 32, 'Name' => 'The Studio', 'Description' => 'I\'m a DJ', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 26, 'CategoryID' => 8, 'Sort' => 34, 'Name' => 'Vinyl', 'Description' => 'Vinyl \'s are here to stay', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 20, 'CategoryID' => 8, 'Sort' => 33, 'Name' => 'Offered', 'Description' => 'I have some music to offer', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 9, 'CategoryID' => 5, 'Sort' => 1400, 'Name' => 'Artists', 'Description' => 'For the artistics', 'MinClassRead' => 800, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 21, 'CategoryID' => 5, 'Sort' => 1700, 'Name' => 'Concerts and Events', 'Description' => 'I\'m off to a gig', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

			['ID' => 3, 'CategoryID' => 10, 'Sort' => 40, 'Name' => 'Help!', 'Description' => 'I fell down and I cant get up', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 34, 'CategoryID' => 10, 'Sort' => 41, 'Name' => 'Editing', 'Description' => 'Quality Control', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 16, 'CategoryID' => 10, 'Sort' => 42, 'Name' => 'Tutorials', 'Description' => 'I would like to share my knowledge', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],
			['ID' => 17, 'CategoryID' => 10, 'Sort' => 43, 'Name' => 'BitTorrent', 'Description' => 'I need to talk about BitTorrent', 'MinClassRead' => 100, 'MinClassWrite' => 100, 'MinClassCreate' => 100],

			['ID' => 4, 'CategoryID' => 20, 'Sort' => 5, 'Name' => 'Trash', 'Description' => 'Every thread ends up here eventually', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
			['ID' => 14, 'CategoryID' => 20, 'Sort' => 101, 'Name' => 'Resolved Bugs', 'Description' => 'The bug I reported has been fixed', 'MinClassRead' => 100, 'MinClassWrite' => 800, 'MinClassCreate' => 800],
		]);

		$this->insert('permissions', [
			[
				'ID' => 2,
				'Level' => 100,
				'Name' => 'User',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_album_votes' => 1,
					'site_edit_wiki' => 1,
					'torrents_add_artist' => 1,
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 3,
				'Level' => 150,
				'Name' => 'Member',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'zip_downloader' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 4,
				'Level' => 200,
				'Name' => 'Power User',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'forums_polls_create' => 1,
					'zip_downloader' => 1,
					'torrents_add_artist' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 5,
				'Level' => 250,
				'Name' => 'Elite',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'forums_polls_create' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'torrents_edit' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 3,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 19,
				'Level' => 201,
				'Name' => 'Artist',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_recommend_own' => 1,
					'MaxCollages' => 0,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '9',
				'Secondary' => 0
			],
			[
				'ID' => 20,
				'Level' => 202,
				'Name' => 'Donor',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'forums_polls_create' => 1,
					'zip_downloader' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '10',
				'Secondary' => 0
			],
			[
				'ID' => 42,
				'Level' => 205,
				'Name' => 'Donor',
				'Values' => serialize([
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'forums_polls_create' => 1,
					'zip_downloader' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '10',
				'Secondary' => 1
			],
			[
				'ID' => 23,
				'Level' => 255,
				'Name' => 'First Line Support',
				'Values' => serialize([
					'site_collages_personal' => 1,
					'site_advanced_top10' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '28',
				'Secondary' => 1
			],
			[
				'ID' => 41,
				'Level' => 257,
				'Name' => 'Recruiter',
				'Values' => serialize([
					'site_send_unlimited_invites' => 1,
					'MaxCollages' => 0,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 30,
				'Level' => 300,
				'Name' => 'Interviewer',
				'Values' => serialize([
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '30',
				'Secondary' => 1
			],
			[
				'ID' => 31,
				'Level' => 310,
				'Name' => 'Torrent Celebrity',
				'Values' => serialize([
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 32,
				'Level' => 320,
				'Name' => 'Designer',
				'Values' => serialize([
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'forums_polls_create' => 1,
					'MaxCollages' => 5,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '33',
				'Secondary' => 1
			],
			[
				'ID' => 33,
				'Level' => 330,
				'Name' => 'Security Team',
				'Values' => serialize([
					'site_send_unlimited_invites' => 1,
					'forums_polls_create' => 1,
					'MaxCollages' => 5,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 34,
				'Level' => 340,
				'Name' => 'IRC Team',
				'Values' => serialize([
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 35,
				'Level' => 350,
				'Name' => 'Shadow Team',
				'Values' => serialize([
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_advanced_top10' => 1,
					'site_can_invite_always' => 1,
					'site_send_unlimited_invites' => 1,
					'site_disable_ip_history' => 1,
					'users_edit_profiles' => 1,
					'users_view_friends' => 1,
					'users_disable_users' => 1,
					'users_disable_posts' => 1,
					'users_disable_any' => 1,
					'users_view_invites' => 1,
					'users_view_email' => 1,
					'users_mod' => 1,
					'admin_advanced_user_search' => 1,
					'MaxCollages' => 0,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 36,
				'Level' => 360,
				'Name' => 'Alpha Team',
				'Values' => serialize([
					'admin_reports' => 1,
					'MaxCollages' => 0,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 37,
				'Level' => 370,
				'Name' => 'Bravo Team',
				'Values' => serialize([
					'MaxCollages' => 0
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 1
			],
			[
				'ID' => 38,
				'Level' => 380,
				'Name' => 'Charlie Team',
				'Values' => serialize([
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_moderate_requests' => 1,
					'site_delete_artist' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'site_tag_aliases_read' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'torrents_fix_ghosts' => 1,
					'MaxCollages' => 2,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '31',
				'Secondary' => 1
			],
			[
				'ID' => 39,
				'Level' => 395,
				'Name' => 'Delta Team',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_can_invite_always' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '35',
				'Secondary' => 1
			],
			[
				'ID' => 25,
				'Level' => 400,
				'Name' => 'Torrent Master',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'forums_polls_create' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'torrents_edit' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 29,
				'Level' => 450,
				'Name' => 'Power TM',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'forums_polls_create' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'torrents_edit' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 5,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 28,
				'Level' => 500,
				'Name' => 'Elite TM',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_send_unlimited_invites' => 1,
					'forums_polls_create' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'torrents_edit' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 26,
				'Level' => 601,
				'Name' => 'VIP',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_send_unlimited_invites' => 1,
					'forums_polls_create' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'torrents_edit' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 27,
				'Level' => 605,
				'Name' => 'Legend',
				'Values' => serialize([
					'MaxCollages' => 1
				]),
				'DisplayStaff' => '0',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 21,
				'Level' => 800,
				'Name' => 'Forum Moderator',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_send_unlimited_invites' => 1,
					'forums_polls_create' => 1,
					'site_moderate_forums' => 1,
					'site_admin_forums' => 1,
					'site_delete_tag' => 1,
					'site_disable_ip_history' => 1,
					'zip_downloader' => 1,
					'site_proxy_images' => 1,
					'site_search_many' => 1,
					'site_forums_double_post' => 1,
					'project_team' => 1,
					'site_tag_aliases_read' => 1,
					'users_edit_titles' => 1,
					'users_edit_avatars' => 1,
					'users_warn' => 1,
					'users_disable_posts' => 1,
					'users_override_paranoia' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'torrents_fix_ghosts' => 1,
					'admin_reports' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 22,
				'Level' => 850,
				'Name' => 'Torrent Moderator',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_send_unlimited_invites' => 1,
					'site_delete_artist' => 1,
					'forums_polls_create' => 1,
					'site_moderate_forums' => 1,
					'site_admin_forums' => 1,
					'site_view_torrent_snatchlist' => 1,
					'site_delete_tag' => 1,
					'site_disable_ip_history' => 1,
					'zip_downloader' => 1,
					'site_proxy_images' => 1,
					'site_search_many' => 1,
					'site_forums_double_post' => 1,
					'project_team' => 1,
					'site_tag_aliases_read' => 1,
					'users_edit_avatars' => 1,
					'users_edit_reset_keys' => 1,
					'users_view_friends' => 1,
					'users_warn' => 1,
					'users_disable_users' => 1,
					'users_disable_posts' => 1,
					'users_view_seedleech' => 1,
					'users_view_uploaded' => 1,
					'users_view_keys' => 1,
					'users_view_ips' => 1,
					'users_view_email' => 1,
					'users_invite_notes' => 1,
					'users_override_paranoia' => 1,
					'users_mod' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_delete_fast' => 1,
					'torrents_search_fast' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'torrents_fix_ghosts' => 1,
					'admin_reports' => 1,
					'admin_advanced_user_search' => 1,
					'admin_clear_cache' => 1,
					'admin_whitelist' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 11,
				'Level' => 900,
				'Name' => 'Moderator',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_delete' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_send_unlimited_invites' => 1,
					'site_moderate_requests' => 1,
					'site_delete_artist' => 1,
					'forums_polls_create' => 1,
					'site_moderate_forums' => 1,
					'site_admin_forums' => 1,
					'site_view_torrent_snatchlist' => 1,
					'site_delete_tag' => 1,
					'site_disable_ip_history' => 1,
					'zip_downloader' => 1,
					'site_proxy_images' => 1,
					'site_search_many' => 1,
					'site_forums_double_post' => 1,
					'project_team' => 1,
					'site_tag_aliases_read' => 1,
					'users_edit_titles' => 1,
					'users_edit_avatars' => 1,
					'users_edit_invites' => 1,
					'users_edit_reset_keys' => 1,
					'users_view_friends' => 1,
					'users_warn' => 1,
					'users_disable_users' => 1,
					'users_disable_posts' => 1,
					'users_disable_any' => 1,
					'users_view_invites' => 1,
					'users_view_seedleech' => 1,
					'users_view_uploaded' => 1,
					'users_view_keys' => 1,
					'users_view_ips' => 1,
					'users_view_email' => 1,
					'users_invite_notes' => 1,
					'users_override_paranoia' => 1,
					'users_logout' => 1,
					'users_mod' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_delete_fast' => 1,
					'torrents_freeleech' => 1,
					'torrents_search_fast' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'torrents_fix_ghosts' => 1,
					'admin_manage_fls' => 1,
					'admin_reports' => 1,
					'admin_advanced_user_search' => 1,
					'admin_clear_cache' => 1,
					'admin_whitelist' => 1,
					'MaxCollages' => 6,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 24,
				'Level' => 950,
				'Name' => 'Developer',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_can_invite_always' => 1,
					'site_send_unlimited_invites' => 1,
					'forums_polls_create' => 1,
					'site_view_flow' => 1,
					'site_view_full_log' => 1,
					'site_view_torrent_snatchlist' => 1,
					'site_recommend_own' => 1,
					'site_manage_recommendations' => 1,
					'site_delete_tag' => 1,
					'zip_downloader' => 1,
					'site_forums_double_post' => 1,
					'MaxCollages' => 1,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '35',
				'Secondary' => 0
			],
			[
				'ID' => 40,
				'Level' => 980,
				'Name' => 'Administrator',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_delete' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_can_invite_always' => 1,
					'site_send_unlimited_invites' => 1,
					'site_moderate_requests' => 1,
					'site_delete_artist' => 1,
					'forums_polls_create' => 1,
					'forums_polls_moderate' => 1,
					'site_moderate_forums' => 1,
					'site_admin_forums' => 1,
					'site_view_flow' => 1,
					'site_view_full_log' => 1,
					'site_view_torrent_snatchlist' => 1,
					'site_recommend_own' => 1,
					'site_manage_recommendations' => 1,
					'site_delete_tag' => 1,
					'site_disable_ip_history' => 1,
					'zip_downloader' => 1,
					'site_proxy_images' => 1,
					'site_search_many' => 1,
					'site_collages_recover' => 1,
					'site_forums_double_post' => 1,
					'project_team' => 1,
					'site_tag_aliases_read' => 1,
					'users_edit_ratio' => 1,
					'users_edit_titles' => 1,
					'users_edit_avatars' => 1,
					'users_edit_invites' => 1,
					'users_edit_watch_hours' => 1,
					'users_edit_reset_keys' => 1,
					'users_edit_profiles' => 1,
					'users_view_friends' => 1,
					'users_reset_own_keys' => 1,
					'users_edit_password' => 1,
					'users_promote_below' => 1,
					'users_warn' => 1,
					'users_disable_users' => 1,
					'users_disable_posts' => 1,
					'users_disable_any' => 1,
					'users_delete_users' => 1,
					'users_view_invites' => 1,
					'users_view_seedleech' => 1,
					'users_view_uploaded' => 1,
					'users_view_keys' => 1,
					'users_view_ips' => 1,
					'users_view_email' => 1,
					'users_invite_notes' => 1,
					'users_override_paranoia' => 1,
					'users_logout' => 1,
					'users_mod' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_delete_fast' => 1,
					'torrents_freeleech' => 1,
					'torrents_search_fast' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'torrents_edit_vanityhouse' => 1,
					'artist_edit_vanityhouse' => 1,
					'torrents_fix_ghosts' => 1,
					'admin_manage_blog' => 1,
					'admin_manage_fls' => 1,
					'admin_reports' => 1,
					'admin_advanced_user_search' => 1,
					'admin_manage_ipbans' => 1,
					'admin_dnu' => 1,
					'admin_clear_cache' => 1,
					'admin_whitelist' => 1,
					'admin_manage_wiki' => 1,
					'MaxCollages' => 5,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 0
			],
			[
				'ID' => 15,
				'Level' => 1000,
				'Name' => 'Sysop',
				'Values' => serialize([
					'site_leech' => 1,
					'site_upload' => 1,
					'site_vote' => 1,
					'site_submit_requests' => 1,
					'site_advanced_search' => 1,
					'site_top10' => 1,
					'site_advanced_top10' => 1,
					'site_album_votes' => 1,
					'site_torrents_notify' => 1,
					'site_collages_create' => 1,
					'site_collages_manage' => 1,
					'site_collages_delete' => 1,
					'site_collages_subscribe' => 1,
					'site_collages_personal' => 1,
					'site_collages_renamepersonal' => 1,
					'site_make_bookmarks' => 1,
					'site_edit_wiki' => 1,
					'site_can_invite_always' => 1,
					'site_send_unlimited_invites' => 1,
					'site_moderate_requests' => 1,
					'site_delete_artist' => 1,
					'site_moderate_forums' => 1,
					'site_admin_forums' => 1,
					'site_forums_double_post' => 1,
					'site_view_flow' => 1,
					'site_view_full_log' => 1,
					'site_view_torrent_snatchlist' => 1,
					'site_recommend_own' => 1,
					'site_manage_recommendations' => 1,
					'site_delete_tag' => 1,
					'site_disable_ip_history' => 1,
					'zip_downloader' => 1,
					'site_debug' => 1,
					'site_proxy_images' => 1,
					'site_search_many' => 1,
					'users_edit_usernames' => 1,
					'users_edit_ratio' => 1,
					'users_edit_own_ratio' => 1,
					'users_edit_titles' => 1,
					'users_edit_avatars' => 1,
					'users_edit_invites' => 1,
					'users_edit_watch_hours' => 1,
					'users_edit_reset_keys' => 1,
					'users_edit_profiles' => 1,
					'users_view_friends' => 1,
					'users_reset_own_keys' => 1,
					'users_edit_password' => 1,
					'users_promote_below' => 1,
					'users_promote_to' => 1,
					'users_give_donor' => 1,
					'users_warn' => 1,
					'users_disable_users' => 1,
					'users_disable_posts' => 1,
					'users_disable_any' => 1,
					'users_delete_users' => 1,
					'users_view_invites' => 1,
					'users_view_seedleech' => 1,
					'users_view_uploaded' => 1,
					'users_view_keys' => 1,
					'users_view_ips' => 1,
					'users_view_email' => 1,
					'users_invite_notes' => 1,
					'users_override_paranoia' => 1,
					'users_logout' => 1,
					'users_make_invisible' => 1,
					'users_mod' => 1,
					'torrents_edit' => 1,
					'torrents_delete' => 1,
					'torrents_delete_fast' => 1,
					'torrents_freeleech' => 1,
					'torrents_search_fast' => 1,
					'torrents_hide_dnu' => 1,
					'torrents_fix_ghosts' => 1,
					'admin_manage_news' => 1,
					'admin_manage_blog' => 1,
					'admin_manage_polls' => 1,
					'admin_manage_forums' => 1,
					'admin_manage_fls' => 1,
					'admin_reports' => 1,
					'admin_advanced_user_search' => 1,
					'admin_create_users' => 1,
					'admin_donor_log' => 1,
					'admin_manage_ipbans' => 1,
					'admin_dnu' => 1,
					'admin_clear_cache' => 1,
					'admin_whitelist' => 1,
					'admin_manage_permissions' => 1,
					'admin_schedule' => 1,
					'admin_login_watch' => 1,
					'admin_manage_wiki' => 1,
					'admin_update_geoip' => 1,
					'site_collages_recover' => 1,
					'torrents_add_artist' => 1,
					'edit_unknowns' => 1,
					'forums_polls_create' => 1,
					'forums_polls_moderate' => 1,
					'project_team' => 1,
					'torrents_edit_vanityhouse' => 1,
					'artist_edit_vanityhouse' => 1,
					'site_tag_aliases_read' => 1,
				]),
				'DisplayStaff' => '1',
				'PermittedForums' => '',
				'Secondary' => 0
			],
		]);

		$this->insert('wiki_articles', [['Title' => 'Wiki', 'Body' => 'Welcome to your new wiki! Hope this works.', 'MinClassRead' => 100, 'MinClassEdit' => 475, 'Date' => 'NOW()', 'Author' => 1]]);
		$this->insert('wiki_aliases', [['Alias' => 'wiki', 'UserID' => 1, 'ArticleID' => 1]]);
		$this->insert('wiki_revisions', [['ID' => 1, 'Revision' => 1, 'Title' => 'Wiki', 'Body' => 'Welcome to your new wiki! Hope this works.', 'Date' => 'NOW()', 'Author' => 1]]);
		$this->insert('tags', [
			['Name' => 'rock', 'TagType' => 'genre', 'Uses' => 0, 'UserID' => 1],
			['Name' => 'pop', 'TagType' => 'genre', 'Uses' => 0, 'UserID' => 1],
			['Name' => 'female.fronted.symphonic.death.metal', 'TagType' => 'genre', 'Uses' => 0, 'UserID' => 1]
		]);

		$this->insert('stylesheets', [
			['Name' => 'Layer cake', 'Description' => 'Grey stylesheet by Emm'],
			['Name' => 'Proton', 'Description' => 'Proton by Protiek'],
			['Name' => 'postmod', 'Description' => 'Upgrade by anorex'],
			['Name' => 'Hydro', 'Description' => 'Hydro'],
			['Name' => 'Kuro', 'Description' => 'Kuro'],
			['Name' => 'Anorex', 'Description' => 'Anorex'],
			['Name' => 'Mono', 'Description' => 'Mono'],
			['Name' => 'Shiro', 'Description' => 'Shiro'],
			['Name' => 'Minimal', 'Description' => 'Minimal'],
			['Name' => 'Whatlove', 'Description' => 'Whatlove'],
			['Name' => 'White.cd', 'Description' => 'White.cd'],
			['Name' => 'GTFO Spaceship', 'Description' => 'gtfo spaceship'],
			['Name' => 'Dark Ambient', 'Description' => 'dark ambient'],
			['Name' => 'Xanax cake', 'Description' => 'Xanax cake'],
			['Name' => 'Haze', 'Description' => 'Haze by Exanurous & apopagasm'],
			['Name' => 'Post Office', 'Description' => 'Post Office by dannymichel'],
			['Name' => 'LinoHaze', 'Description' => 'LinoHaze by linotype'],
			['Name' => 'ApolloStage', 'Description' => 'ApolloStage by burtoo', 'Default' => '1'],
			['Name' => 'ApolloStage Coffee', 'Description' => 'ApolloStage by burtoo'],
			['Name' => 'ApolloStage Sunset', 'Description' => 'ApolloStage Sunset by burtoo'],
			['Name' => 'Apollo Mat', 'Description' => 'Apollo Mat by salem']
		]);

		$this->insert('schedule', [['NextHour' => 0, 'NextDay' => 0, 'NextBiWeekly' => 0]]);

		$this->execute("SET FOREIGN_KEY_CHECKS = 1;");
	}
}
