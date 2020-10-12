-- MySQL dump 10.16  Distrib 10.1.44-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: gazelle
-- ------------------------------------------------------
-- Server version	10.3.23-MariaDB-1:10.3.23+maria~focal

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `api_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_applications` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `UserID` int(10) NOT NULL,
  `Token` char(32) NOT NULL,
  `Name` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_users` (
  `UserID` int(10) NOT NULL,
  `AppID` int(10) NOT NULL,
  `Token` char(32) NOT NULL,
  `State` enum('0','1','2') NOT NULL DEFAULT '0',
  `Time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Access` text NOT NULL,
  PRIMARY KEY (`UserID`,`AppID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `applicant`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicant` (
  `ID` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `RoleID` int(4) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `ThreadID` int(6) unsigned NOT NULL,
  `Body` text NOT NULL,
  `Created` timestamp NOT NULL DEFAULT current_timestamp(),
  `Modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `Resolved` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `RoleID` (`RoleID`),
  KEY `ThreadID` (`ThreadID`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `applicant_ibfk_1` FOREIGN KEY (`RoleID`) REFERENCES `applicant_role` (`ID`),
  CONSTRAINT `applicant_ibfk_2` FOREIGN KEY (`ThreadID`) REFERENCES `thread` (`ID`),
  CONSTRAINT `applicant_ibfk_3` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `applicant_role`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicant_role` (
  `ID` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `Title` varchar(40) NOT NULL,
  `Published` tinyint(4) NOT NULL DEFAULT 0,
  `Description` text NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT current_timestamp(),
  `Modified` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ar_title_uidx` (`Title`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `applicant_role_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artist_discogs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artist_discogs` (
  `artist_discogs_id` int(10) unsigned NOT NULL,
  `artist_id` int(10) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0,
  `sequence` tinyint(3) unsigned NOT NULL,
  `stem` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`artist_discogs_id`),
  UNIQUE KEY `artist_id` (`artist_id`),
  UNIQUE KEY `name` (`name`),
  KEY `user_id` (`user_id`),
  KEY `ad_stem_idx` (`stem`),
  CONSTRAINT `artist_discogs_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists_group` (`ArtistID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `artist_discogs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artist_usage`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artist_usage` (
  `artist_id` int(10) NOT NULL,
  `role` enum('0','1','2','3','4','5','6','7') NOT NULL,
  `uses` int(10) unsigned NOT NULL,
  PRIMARY KEY (`artist_id`,`role`),
  CONSTRAINT `artist_usage_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists_group` (`ArtistID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_alias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_alias` (
  `AliasID` int(10) NOT NULL AUTO_INCREMENT,
  `ArtistID` int(10) NOT NULL,
  `Name` varchar(200) DEFAULT NULL,
  `Redirect` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`AliasID`),
  KEY `ArtistID` (`ArtistID`,`Name`),
  KEY `name_idx` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_group`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_group` (
  `ArtistID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(200) DEFAULT NULL,
  `RevisionID` int(12) DEFAULT NULL,
  `VanityHouse` tinyint(1) NOT NULL DEFAULT 0,
  `LastCommentID` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ArtistID`),
  KEY `Name` (`Name`,`RevisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_similar`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_similar` (
  `ArtistID` int(10) NOT NULL DEFAULT 0,
  `SimilarID` int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ArtistID`,`SimilarID`),
  KEY `as_similarid_idx` (`SimilarID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_similar_scores`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_similar_scores` (
  `SimilarID` int(12) NOT NULL AUTO_INCREMENT,
  `Score` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`SimilarID`),
  KEY `Score` (`Score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_similar_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_similar_votes` (
  `SimilarID` int(12) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Way` enum('up','down') NOT NULL DEFAULT 'up',
  PRIMARY KEY (`SimilarID`,`UserID`,`Way`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artists_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artists_tags` (
  `TagID` int(10) NOT NULL DEFAULT 0,
  `ArtistID` int(10) NOT NULL DEFAULT 0,
  `PositiveVotes` int(6) NOT NULL DEFAULT 1,
  `NegativeVotes` int(6) NOT NULL DEFAULT 1,
  `UserID` int(10) NOT NULL,
  PRIMARY KEY (`TagID`,`ArtistID`),
  KEY `TagID` (`TagID`,`ArtistID`,`PositiveVotes`,`NegativeVotes`,`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bad_passwords`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bad_passwords` (
  `Password` char(32) NOT NULL,
  PRIMARY KEY (`Password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` text NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `ThreadID` int(10) unsigned DEFAULT NULL,
  `Important` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_history` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `ItemID` int(6) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `Price` int(10) unsigned NOT NULL,
  `OtherUserID` int(10) unsigned DEFAULT NULL,
  `PurchaseDate` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `bonus_history_fk_user` (`UserID`),
  KEY `bonus_history_fk_item` (`ItemID`),
  CONSTRAINT `bonus_history_fk_item` FOREIGN KEY (`ItemID`) REFERENCES `bonus_item` (`ID`),
  CONSTRAINT `bonus_history_fk_user` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_item` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `Price` int(10) unsigned NOT NULL,
  `Amount` int(2) unsigned DEFAULT NULL,
  `MinClass` int(6) unsigned NOT NULL DEFAULT 0,
  `FreeClass` int(6) unsigned NOT NULL DEFAULT 999999,
  `Label` varchar(32) NOT NULL,
  `Title` varchar(64) NOT NULL,
  `sequence` int(6) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Label` (`Label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_pool`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_pool` (
  `bonus_pool_id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `since_date` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `until_date` datetime NOT NULL,
  `total` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`bonus_pool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_pool_contrib`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_pool_contrib` (
  `bonus_pool_contrib_id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `bonus_pool_id` int(6) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `amount_recv` float NOT NULL,
  `amount_sent` float NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bonus_pool_contrib_id`),
  KEY `bonus_pool_contrib_ibfk_1` (`bonus_pool_id`),
  KEY `bonus_pool_contrib_ibfk_2` (`user_id`),
  CONSTRAINT `bonus_pool_contrib_ibfk_1` FOREIGN KEY (`bonus_pool_id`) REFERENCES `bonus_pool` (`bonus_pool_id`),
  CONSTRAINT `bonus_pool_contrib_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks_artists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks_artists` (
  `UserID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  KEY `UserID` (`UserID`),
  KEY `ArtistID` (`ArtistID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks_collages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks_collages` (
  `UserID` int(10) NOT NULL,
  `CollageID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CollageID`,`UserID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks_requests` (
  `UserID` int(10) NOT NULL,
  `RequestID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`RequestID`,`UserID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks_torrents` (
  `UserID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`GroupID`),
  UNIQUE KEY `groups_users` (`GroupID`,`UserID`),
  KEY `UserID` (`UserID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) DEFAULT NULL,
  `Body` mediumtext DEFAULT NULL,
  `Category` tinyint(1) DEFAULT NULL,
  `StartDate` datetime DEFAULT NULL,
  `EndDate` datetime DEFAULT NULL,
  `AddedBy` int(10) DEFAULT NULL,
  `Importance` tinyint(1) DEFAULT NULL,
  `Team` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `changelog`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `changelog` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Message` text NOT NULL,
  `Author` varchar(30) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collage_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collage_attr` (
  `ID` int(6) NOT NULL AUTO_INCREMENT,
  `Name` varchar(24) NOT NULL,
  `Description` varchar(500) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collage_has_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collage_has_attr` (
  `CollageID` int(10) NOT NULL,
  `CollageAttrID` int(6) NOT NULL,
  PRIMARY KEY (`CollageID`,`CollageAttrID`),
  KEY `CollageAttrID` (`CollageAttrID`),
  CONSTRAINT `collage_has_attr_ibfk_1` FOREIGN KEY (`CollageID`) REFERENCES `collages` (`ID`),
  CONSTRAINT `collage_has_attr_ibfk_2` FOREIGN KEY (`CollageAttrID`) REFERENCES `collage_attr` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collages` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL DEFAULT '',
  `Description` text NOT NULL,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `NumTorrents` int(4) NOT NULL DEFAULT 0,
  `Deleted` enum('0','1') DEFAULT '0',
  `Locked` enum('0','1') NOT NULL DEFAULT '0',
  `CategoryID` int(2) NOT NULL DEFAULT 1,
  `TagList` varchar(500) NOT NULL DEFAULT '',
  `MaxGroups` int(10) NOT NULL DEFAULT 0,
  `MaxGroupsPerUser` int(10) NOT NULL DEFAULT 0,
  `Featured` tinyint(4) NOT NULL DEFAULT 0,
  `Subscribers` int(10) DEFAULT 0,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `UserID` (`UserID`),
  KEY `CategoryID` (`CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collages_artists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collages_artists` (
  `CollageID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Sort` int(10) NOT NULL DEFAULT 0,
  `AddedOn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CollageID`,`ArtistID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collages_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collages_torrents` (
  `CollageID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Sort` int(10) NOT NULL DEFAULT 0,
  `AddedOn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CollageID`,`GroupID`),
  KEY `UserID` (`UserID`),
  KEY `group_idx` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Body` mediumtext DEFAULT NULL,
  `EditedUserID` int(10) DEFAULT NULL,
  `EditedTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Page` (`Page`,`PageID`),
  KEY `AuthorID` (`AuthorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments_edits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments_edits` (
  `Page` enum('forums','artist','collages','requests','torrents') DEFAULT NULL,
  `PostID` int(10) DEFAULT NULL,
  `EditUser` int(10) DEFAULT NULL,
  `EditTime` datetime DEFAULT NULL,
  `Body` mediumtext DEFAULT NULL,
  KEY `PostHistory` (`Page`,`PostID`,`EditTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contest`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contest` (
  `contest_id` int(11) NOT NULL AUTO_INCREMENT,
  `contest_type_id` int(11) NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 NOT NULL,
  `banner` varchar(128) CHARACTER SET ascii DEFAULT NULL,
  `date_begin` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `display` int(11) NOT NULL DEFAULT 50,
  `max_tracked` int(11) NOT NULL DEFAULT 500,
  `description` mediumtext DEFAULT NULL,
  PRIMARY KEY (`contest_id`),
  UNIQUE KEY `Name` (`name`),
  KEY `contest_type_fk` (`contest_type_id`),
  KEY `dateend_idx` (`date_end`),
  CONSTRAINT `contest_type_fk` FOREIGN KEY (`contest_type_id`) REFERENCES `contest_type` (`contest_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contest_has_bonus_pool`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contest_has_bonus_pool` (
  `bonus_pool_id` int(6) unsigned NOT NULL,
  `contest_id` int(11) NOT NULL,
  `status` enum('open','ready','paid') NOT NULL DEFAULT 'open',
  `bonus_contest` int(4) unsigned NOT NULL DEFAULT 15,
  `bonus_user` int(4) unsigned NOT NULL DEFAULT 5,
  `bonus_per_entry` int(4) unsigned NOT NULL DEFAULT 80,
  PRIMARY KEY (`bonus_pool_id`,`contest_id`),
  KEY `contest_has_bonus_pool_ibfk_2` (`contest_id`),
  CONSTRAINT `contest_has_bonus_pool_ibfk_1` FOREIGN KEY (`bonus_pool_id`) REFERENCES `bonus_pool` (`bonus_pool_id`),
  CONSTRAINT `contest_has_bonus_pool_ibfk_2` FOREIGN KEY (`contest_id`) REFERENCES `contest` (`contest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contest_leaderboard`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contest_leaderboard` (
  `contest_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entry_count` int(11) NOT NULL,
  `last_entry_id` int(11) NOT NULL,
  KEY `contest_fk` (`contest_id`),
  KEY `flac_upload_idx` (`entry_count`,`user_id`),
  CONSTRAINT `contest_leaderboard_fk` FOREIGN KEY (`contest_id`) REFERENCES `contest` (`contest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contest_type`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contest_type` (
  `contest_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`contest_type_id`),
  UNIQUE KEY `Name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cover_art`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cover_art` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `GroupID` int(10) NOT NULL,
  `Image` varchar(255) NOT NULL DEFAULT '',
  `Summary` varchar(100) DEFAULT NULL,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Time` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `GroupID` (`GroupID`,`Image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents` (
  `ID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `UserID` int(10) DEFAULT NULL,
  `Media` varchar(20) DEFAULT NULL,
  `Format` varchar(10) DEFAULT NULL,
  `Encoding` varchar(15) DEFAULT NULL,
  `Remastered` enum('0','1') NOT NULL,
  `RemasterYear` int(4) DEFAULT NULL,
  `RemasterTitle` varchar(80) NOT NULL,
  `RemasterCatalogueNumber` varchar(80) NOT NULL,
  `RemasterRecordLabel` varchar(80) NOT NULL,
  `Scene` enum('0','1') NOT NULL,
  `HasLog` enum('0','1') NOT NULL,
  `HasCue` enum('0','1') NOT NULL,
  `HasLogDB` enum('0','1') NOT NULL,
  `LogScore` int(6) NOT NULL,
  `LogChecksum` enum('0','1') NOT NULL,
  `info_hash` blob NOT NULL,
  `FileCount` int(6) NOT NULL,
  `FileList` mediumtext NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `Size` bigint(12) NOT NULL,
  `FreeTorrent` enum('0','1','2') NOT NULL,
  `FreeLeechType` enum('0','1','2','3','4','5','6','7') NOT NULL,
  `Time` datetime DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `LastReseedRequest` datetime DEFAULT NULL,
  `TranscodedFrom` int(10) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_bad_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_bad_files` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_bad_folders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_bad_folders` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_bad_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_bad_tags` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_cassette_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_cassette_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_leech_stats`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_leech_stats` (
  `TorrentID` int(10) NOT NULL,
  `Seeders` int(6) unsigned NOT NULL DEFAULT 0,
  `Leechers` int(6) unsigned NOT NULL DEFAULT 0,
  `Snatched` int(6) unsigned NOT NULL DEFAULT 0,
  `Balance` bigint(20) NOT NULL DEFAULT 0,
  `last_action` datetime DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  CONSTRAINT `deleted_torrents_leech_stats_ibfk_1` FOREIGN KEY (`TorrentID`) REFERENCES `deleted_torrents` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_lossymaster_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_lossymaster_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_lossyweb_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_lossyweb_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_torrents_missing_lineage`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_torrents_missing_lineage` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deleted_users_notify_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_users_notify_torrents` (
  `UserID` int(10) NOT NULL,
  `FilterID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UnRead` int(4) NOT NULL,
  PRIMARY KEY (`UserID`,`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `do_not_upload`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `do_not_upload` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Comment` varchar(255) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Sequence` mediumint(8) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`),
  KEY `sequence_idx` (`Sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `donations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donations` (
  `UserID` int(10) NOT NULL,
  `Amount` decimal(6,2) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Currency` varchar(5) NOT NULL DEFAULT 'USD',
  `Source` varchar(30) NOT NULL DEFAULT '',
  `Reason` mediumtext NOT NULL,
  `Rank` int(10) DEFAULT 0,
  `AddedBy` int(10) DEFAULT 0,
  `TotalRank` int(10) DEFAULT 0,
  `xbt` decimal(24,12) DEFAULT NULL,
  `donations_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`donations_id`),
  KEY `UserID` (`UserID`),
  KEY `Time` (`Time`),
  KEY `don_time_idx` (`Time`),
  KEY `don_userid_time_idx` (`UserID`,`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `donations_bitcoin`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donations_bitcoin` (
  `BitcoinAddress` varchar(34) NOT NULL,
  `Amount` decimal(24,8) NOT NULL,
  KEY `BitcoinAddress` (`BitcoinAddress`,`Amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `donor_forum_usernames`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donor_forum_usernames` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Prefix` varchar(30) NOT NULL DEFAULT '',
  `Suffix` varchar(30) NOT NULL DEFAULT '',
  `UseComma` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `donor_rewards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donor_rewards` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `IconMouseOverText` varchar(200) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `AvatarMouseOverText` varchar(200) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `CustomIcon` varchar(200) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `SecondAvatar` varchar(200) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `CustomIconLink` varchar(200) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `ProfileInfo1` text CHARACTER SET utf8 NOT NULL,
  `ProfileInfo2` text CHARACTER SET utf8 NOT NULL,
  `ProfileInfo3` text CHARACTER SET utf8 NOT NULL,
  `ProfileInfo4` text CHARACTER SET utf8 NOT NULL,
  `ProfileInfoTitle1` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ProfileInfoTitle2` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ProfileInfoTitle3` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ProfileInfoTitle4` varchar(255) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dupe_groups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dupe_groups` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Comments` text DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_blacklist`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_blacklist` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `UserID` int(10) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Comment` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `featured_albums`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `featured_albums` (
  `GroupID` int(10) NOT NULL DEFAULT 0,
  `ThreadID` int(10) NOT NULL DEFAULT 0,
  `Title` varchar(35) NOT NULL DEFAULT '',
  `Started` datetime NOT NULL DEFAULT current_timestamp(),
  `Ended` datetime DEFAULT NULL,
  `Type` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `CategoryID` tinyint(2) DEFAULT NULL,
  `Sort` int(6) unsigned NOT NULL,
  `Name` varchar(40) NOT NULL DEFAULT '',
  `Description` varchar(255) DEFAULT '',
  `MinClassRead` int(4) NOT NULL DEFAULT 0,
  `MinClassWrite` int(4) NOT NULL DEFAULT 0,
  `MinClassCreate` int(4) NOT NULL DEFAULT 0,
  `NumTopics` int(10) NOT NULL DEFAULT 0,
  `NumPosts` int(10) NOT NULL DEFAULT 0,
  `LastPostID` int(10) NOT NULL DEFAULT 0,
  `LastPostAuthorID` int(10) NOT NULL DEFAULT 0,
  `LastPostTopicID` int(10) NOT NULL DEFAULT 0,
  `LastPostTime` datetime DEFAULT NULL,
  `AutoLock` enum('0','1') DEFAULT '1',
  `AutoLockWeeks` int(3) unsigned NOT NULL DEFAULT 4,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_categories` (
  `ID` tinyint(4) NOT NULL,
  `Name` varchar(40) NOT NULL DEFAULT '',
  `Sort` int(6) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_last_read_topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_last_read_topics` (
  `UserID` int(10) NOT NULL,
  `TopicID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`TopicID`),
  KEY `TopicID` (`TopicID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_polls`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_polls` (
  `TopicID` int(10) unsigned NOT NULL,
  `Question` varchar(255) NOT NULL,
  `Answers` text NOT NULL,
  `Featured` datetime DEFAULT NULL,
  `Closed` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`TopicID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_polls_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_polls_votes` (
  `TopicID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `Vote` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`TopicID`,`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_posts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_posts` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `TopicID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Body` mediumtext DEFAULT NULL,
  `EditedUserID` int(10) DEFAULT NULL,
  `EditedTime` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `TopicID` (`TopicID`),
  KEY `AuthorID` (`AuthorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_topic_notes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_topic_notes` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `TopicID` int(10) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `AddedTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Body` mediumtext DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `TopicID` (`TopicID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_topics`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_topics` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Title` varchar(150) NOT NULL,
  `AuthorID` int(10) NOT NULL,
  `IsLocked` enum('0','1') NOT NULL DEFAULT '0',
  `IsSticky` enum('0','1') NOT NULL DEFAULT '0',
  `ForumID` int(3) NOT NULL,
  `NumPosts` int(10) NOT NULL DEFAULT 0,
  `LastPostID` int(10) NOT NULL DEFAULT 0,
  `LastPostTime` datetime NOT NULL DEFAULT current_timestamp(),
  `LastPostAuthorID` int(10) NOT NULL,
  `StickyPostID` int(10) NOT NULL DEFAULT 0,
  `Ranking` tinyint(2) DEFAULT 0,
  `CreatedTime` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `AuthorID` (`AuthorID`),
  KEY `ForumID` (`ForumID`),
  KEY `IsSticky` (`IsSticky`),
  KEY `LastPostID` (`LastPostID`),
  KEY `CreatedTime` (`CreatedTime`),
  KEY `ft_fid_sticky_idx` (`ForumID`,`IsSticky`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forums_transitions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forums_transitions` (
  `forums_transitions_id` int(10) NOT NULL AUTO_INCREMENT,
  `source` int(6) unsigned NOT NULL,
  `destination` int(6) unsigned NOT NULL,
  `label` varchar(20) NOT NULL,
  `permission_levels` varchar(50) NOT NULL,
  `permission_class` int(10) unsigned NOT NULL DEFAULT 800,
  `permissions` varchar(100) NOT NULL DEFAULT '',
  `user_ids` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`forums_transitions_id`),
  KEY `source` (`source`),
  KEY `destination` (`destination`),
  CONSTRAINT `forums_transitions_ibfk_1` FOREIGN KEY (`source`) REFERENCES `forums` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forums_transitions_ibfk_2` FOREIGN KEY (`destination`) REFERENCES `forums` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `friends`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friends` (
  `UserID` int(10) unsigned NOT NULL,
  `FriendID` int(10) unsigned NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`UserID`,`FriendID`),
  KEY `FriendID` (`FriendID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `geoip_country`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `geoip_country` (
  `StartIP` int(11) unsigned NOT NULL,
  `EndIP` int(11) unsigned NOT NULL,
  `Code` varchar(2) NOT NULL,
  PRIMARY KEY (`StartIP`,`EndIP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_log` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Info` mediumtext DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Hidden` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `GroupID` (`GroupID`),
  KEY `TorrentID` (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invite_tree`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invite_tree` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `InviterID` int(10) DEFAULT NULL,
  `TreePosition` int(8) NOT NULL DEFAULT 1,
  `TreeID` int(10) NOT NULL DEFAULT 1,
  `TreeLevel` int(3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`),
  KEY `TreePosition` (`TreePosition`),
  KEY `TreeID` (`TreeID`),
  KEY `TreeLevel` (`TreeLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invites`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invites` (
  `InviterID` int(10) NOT NULL DEFAULT 0,
  `InviteKey` char(32) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Expires` datetime NOT NULL DEFAULT current_timestamp(),
  `Reason` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`InviteKey`),
  KEY `Expires` (`Expires`),
  KEY `InviterID` (`InviterID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ip_bans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_bans` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `FromIP` int(11) unsigned NOT NULL,
  `ToIP` int(11) unsigned NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `FromIP_2` (`FromIP`,`ToIP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `irc_channels`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `irc_channels` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  `Sort` int(11) NOT NULL DEFAULT 0,
  `MinLevel` int(10) unsigned NOT NULL DEFAULT 0,
  `Classes` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `label_aliases`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `label_aliases` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `BadLabel` varchar(100) NOT NULL,
  `AliasLabel` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `BadLabel` (`BadLabel`),
  KEY `AliasLabel` (`AliasLabel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lastfm_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lastfm_users` (
  `ID` int(10) unsigned NOT NULL,
  `Username` varchar(20) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locked_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locked_accounts` (
  `UserID` int(10) unsigned NOT NULL,
  `Type` tinyint(1) NOT NULL,
  PRIMARY KEY (`UserID`),
  CONSTRAINT `locked_accounts_fk` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Message` varchar(400) NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `IP` varchar(15) NOT NULL,
  `LastAttempt` datetime NOT NULL DEFAULT current_timestamp(),
  `Attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `BannedUntil` datetime DEFAULT NULL,
  `Bans` int(10) unsigned NOT NULL,
  `capture` varchar(20) CHARACTER SET utf8mb4 DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `IP` (`IP`),
  KEY `attempts_idx` (`Attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nav_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nav_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `target` varchar(200) NOT NULL,
  `tests` varchar(100) NOT NULL,
  `test_user` tinyint(1) NOT NULL,
  `mandatory` tinyint(1) NOT NULL,
  `initial` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` mediumtext NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_reminders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_reminders` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Text` varchar(100) NOT NULL,
  `Expiry` date NOT NULL DEFAULT curdate(),
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  `AnnualRent` float(24,12) NOT NULL DEFAULT 0.000000000000,
  `cc` enum('XBT','EUR','USD') NOT NULL DEFAULT 'USD',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `periodic_task`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodic_task` (
  `periodic_task_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `classname` varchar(32) NOT NULL,
  `description` varchar(255) NOT NULL,
  `period` int(10) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_sane` tinyint(1) NOT NULL DEFAULT 1,
  `is_debug` tinyint(1) NOT NULL DEFAULT 0,
  `run_now` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`periodic_task_id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `classname` (`classname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `periodic_task_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodic_task_history` (
  `periodic_task_history_id` int(20) unsigned NOT NULL AUTO_INCREMENT,
  `periodic_task_id` int(10) unsigned NOT NULL,
  `launch_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('running','completed','failed') NOT NULL DEFAULT 'running',
  `num_errors` int(10) unsigned NOT NULL DEFAULT 0,
  `num_items` int(10) unsigned NOT NULL DEFAULT 0,
  `duration_ms` int(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`periodic_task_history_id`),
  KEY `periodic_task_id` (`periodic_task_id`),
  CONSTRAINT `periodic_task_history_ibfk_1` FOREIGN KEY (`periodic_task_id`) REFERENCES `periodic_task` (`periodic_task_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `periodic_task_history_event`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodic_task_history_event` (
  `periodic_task_history_event_id` int(20) unsigned NOT NULL AUTO_INCREMENT,
  `periodic_task_history_id` int(20) unsigned NOT NULL,
  `severity` enum('debug','info','error') NOT NULL,
  `event_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `event` varchar(255) NOT NULL,
  `reference` int(10) unsigned NOT NULL,
  PRIMARY KEY (`periodic_task_history_event_id`),
  KEY `periodic_task_history_id` (`periodic_task_history_id`),
  CONSTRAINT `periodic_task_history_event_ibfk_1` FOREIGN KEY (`periodic_task_history_id`) REFERENCES `periodic_task_history` (`periodic_task_history_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permission_rate_limit`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_rate_limit` (
  `permission_id` int(10) unsigned NOT NULL,
  `overshoot` int(11) NOT NULL,
  `factor` float NOT NULL,
  PRIMARY KEY (`permission_id`),
  CONSTRAINT `permission_rate_limit_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Level` int(10) unsigned NOT NULL,
  `Name` varchar(25) NOT NULL,
  `Values` text NOT NULL,
  `DisplayStaff` enum('0','1') NOT NULL DEFAULT '0',
  `PermittedForums` varchar(150) NOT NULL DEFAULT '',
  `Secondary` tinyint(4) NOT NULL DEFAULT 0,
  `StaffGroup` int(3) unsigned DEFAULT NULL,
  `badge` varchar(5) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Level` (`Level`),
  KEY `DisplayStaff` (`DisplayStaff`),
  KEY `StaffGroup` (`StaffGroup`),
  KEY `secondary_name_idx` (`Secondary`,`Name`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`StaffGroup`) REFERENCES `staff_groups` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `phinxlog`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pm_conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pm_conversations` (
  `ID` int(12) NOT NULL AUTO_INCREMENT,
  `Subject` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pm_conversations_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pm_conversations_users` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `ConvID` int(12) NOT NULL DEFAULT 0,
  `InInbox` enum('1','0') NOT NULL,
  `InSentbox` enum('1','0') NOT NULL,
  `SentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ReceivedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `UnRead` enum('1','0') NOT NULL DEFAULT '1',
  `Sticky` enum('1','0') NOT NULL DEFAULT '0',
  `ForwardedTo` int(12) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`ConvID`),
  KEY `InInbox` (`InInbox`),
  KEY `ConvID` (`ConvID`),
  KEY `UserID` (`UserID`),
  KEY `pcu_userid_unread_ininbox` (`UserID`,`UnRead`,`InInbox`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pm_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pm_messages` (
  `ID` int(12) NOT NULL AUTO_INCREMENT,
  `ConvID` int(12) NOT NULL DEFAULT 0,
  `SentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `SenderID` int(10) NOT NULL DEFAULT 0,
  `Body` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `ConvID` (`ConvID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_notifications_usage`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_notifications_usage` (
  `PushService` varchar(10) NOT NULL,
  `TimesUsed` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`PushService`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ratelimit_torrent`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ratelimit_torrent` (
  `ratelimit_torrent_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `torrent_id` int(10) NOT NULL,
  `logged` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ratelimit_torrent_id`),
  KEY `user_id` (`user_id`),
  KEY `torrent_id` (`torrent_id`),
  CONSTRAINT `ratelimit_torrent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`),
  CONSTRAINT `ratelimit_torrent_ibfk_2` FOREIGN KEY (`torrent_id`) REFERENCES `torrents` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `referral_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_accounts` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Site` varchar(100) NOT NULL,
  `URL` varchar(100) NOT NULL,
  `User` varchar(100) NOT NULL,
  `Password` varchar(196) NOT NULL,
  `Active` tinyint(1) NOT NULL,
  `Cookie` varchar(1024) NOT NULL,
  `Type` int(3) unsigned NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `referral_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_users` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `Username` varchar(100) NOT NULL,
  `Site` varchar(100) NOT NULL,
  `Created` timestamp NOT NULL DEFAULT current_timestamp(),
  `Joined` timestamp NULL DEFAULT NULL,
  `IP` varchar(15) NOT NULL,
  `InviteKey` varchar(32) NOT NULL,
  `Active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `ru_invitekey_idx` (`InviteKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `release_type`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `release_type` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `ThingID` int(10) unsigned NOT NULL DEFAULT 0,
  `Type` varchar(30) DEFAULT NULL,
  `Comment` text DEFAULT NULL,
  `ResolverID` int(10) unsigned NOT NULL DEFAULT 0,
  `Status` enum('New','InProgress','Resolved') DEFAULT 'New',
  `ResolvedTime` datetime DEFAULT NULL,
  `ReportedTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Reason` text NOT NULL,
  `ClaimerID` int(10) unsigned NOT NULL DEFAULT 0,
  `Notes` text NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Status` (`Status`),
  KEY `ResolverID` (`ResolverID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reportsv2`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportsv2` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ReporterID` int(10) unsigned NOT NULL DEFAULT 0,
  `TorrentID` int(10) unsigned NOT NULL DEFAULT 0,
  `Type` varchar(20) DEFAULT '',
  `UserComment` text DEFAULT NULL,
  `ResolverID` int(10) unsigned NOT NULL DEFAULT 0,
  `Status` enum('New','InProgress','Resolved') DEFAULT 'New',
  `ReportedTime` datetime NOT NULL DEFAULT current_timestamp(),
  `LastChangeTime` datetime NOT NULL DEFAULT current_timestamp(),
  `ModComment` text DEFAULT NULL,
  `Track` text DEFAULT NULL,
  `Image` text DEFAULT NULL,
  `ExtraID` text DEFAULT NULL,
  `Link` text DEFAULT NULL,
  `LogMessage` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Status` (`Status`),
  KEY `Type` (`Type`(1)),
  KEY `LastChangeTime` (`LastChangeTime`),
  KEY `TorrentID` (`TorrentID`),
  KEY `resolver_idx` (`ResolverID`),
  KEY `r2_torrentid_status` (`TorrentID`,`Status`),
  KEY `r2_lastchange_resolver_idx` (`LastChangeTime`,`ResolverID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
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
  `FillerID` int(10) unsigned NOT NULL DEFAULT 0,
  `TorrentID` int(10) unsigned NOT NULL DEFAULT 0,
  `TimeFilled` datetime DEFAULT NULL,
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `RecordLabel` varchar(80) DEFAULT NULL,
  `GroupID` int(10) DEFAULT NULL,
  `OCLC` varchar(55) NOT NULL DEFAULT '',
  `Checksum` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `Userid` (`UserID`),
  KEY `Filled` (`TorrentID`),
  KEY `FillerID` (`FillerID`),
  KEY `TimeAdded` (`TimeAdded`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requests_artists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests_artists` (
  `RequestID` int(10) unsigned NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `AliasID` int(10) NOT NULL,
  `Importance` enum('1','2','3','4','5','6','7') DEFAULT NULL,
  PRIMARY KEY (`RequestID`,`AliasID`),
  KEY `artistid_idx` (`ArtistID`),
  KEY `aliasid_idx` (`AliasID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requests_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests_tags` (
  `TagID` int(10) NOT NULL DEFAULT 0,
  `RequestID` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`TagID`,`RequestID`),
  KEY `TagID` (`TagID`),
  KEY `RequestID` (`RequestID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requests_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests_votes` (
  `RequestID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Bounty` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`RequestID`,`UserID`),
  KEY `RequestID` (`RequestID`),
  KEY `UserID` (`UserID`),
  KEY `Bounty` (`Bounty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule` (
  `NextHour` int(2) NOT NULL DEFAULT 0,
  `NextDay` int(2) NOT NULL DEFAULT 0,
  `NextBiWeekly` int(2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_options`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_options` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(64) NOT NULL,
  `Value` tinytext NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `name_index` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_a`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sphinx_a` (
  `gid` int(11) DEFAULT NULL,
  `aname` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_delta`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sphinx_delta` (
  `ID` int(10) NOT NULL,
  `GroupID` int(11) NOT NULL DEFAULT 0,
  `GroupName` varchar(255) DEFAULT NULL,
  `ArtistName` varchar(2048) DEFAULT NULL,
  `TagList` varchar(728) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `CatalogueNumber` varchar(50) DEFAULT NULL,
  `RecordLabel` varchar(50) DEFAULT NULL,
  `CategoryID` tinyint(2) DEFAULT NULL,
  `Time` int(12) DEFAULT NULL,
  `ReleaseType` tinyint(4) DEFAULT NULL,
  `Size` bigint(20) DEFAULT NULL,
  `Snatched` int(10) DEFAULT NULL,
  `Seeders` int(10) DEFAULT NULL,
  `Leechers` int(10) DEFAULT NULL,
  `LogScore` int(3) DEFAULT NULL,
  `Scene` tinyint(1) NOT NULL DEFAULT 0,
  `VanityHouse` tinyint(1) NOT NULL DEFAULT 0,
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
  `FileList` mediumtext DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `VoteScore` float NOT NULL DEFAULT 0,
  `LastChanged` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `GroupID` (`GroupID`),
  KEY `Size` (`Size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_index_last_pos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sphinx_index_last_pos` (
  `Type` varchar(16) NOT NULL DEFAULT '',
  `ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sphinx_requests` (
  `ID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `TimeAdded` int(12) unsigned NOT NULL DEFAULT 0,
  `LastVote` int(12) unsigned NOT NULL DEFAULT 0,
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
  `FillerID` int(10) unsigned NOT NULL DEFAULT 0,
  `TorrentID` int(10) unsigned NOT NULL DEFAULT 0,
  `TimeFilled` int(12) unsigned DEFAULT NULL,
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `Bounty` bigint(20) unsigned NOT NULL DEFAULT 0,
  `Votes` int(10) unsigned NOT NULL DEFAULT 0,
  `RecordLabel` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_requests_delta`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sphinx_requests_delta` (
  `ID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
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
  `FillerID` int(10) unsigned NOT NULL DEFAULT 0,
  `TorrentID` int(10) unsigned NOT NULL DEFAULT 0,
  `TimeFilled` int(12) unsigned DEFAULT NULL,
  `Visible` binary(1) NOT NULL DEFAULT '1',
  `Bounty` bigint(20) unsigned NOT NULL DEFAULT 0,
  `Votes` int(10) unsigned NOT NULL DEFAULT 0,
  `RecordLabel` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Userid` (`UserID`),
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_t`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `remyear` smallint(6) NOT NULL DEFAULT 0,
  `remtitle` varchar(80) NOT NULL,
  `remrlabel` varchar(80) NOT NULL,
  `remcnumber` varchar(80) NOT NULL,
  `filelist` mediumtext DEFAULT NULL,
  `remident` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gid_remident` (`gid`,`remident`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sphinx_tg`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_blog`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_blog` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Body` text NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `Time` (`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_blog_visits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_blog_visits` (
  `UserID` int(10) unsigned NOT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`UserID`),
  CONSTRAINT `staff_blog_visits_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_groups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_groups` (
  `ID` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `Sort` int(4) unsigned NOT NULL,
  `Name` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_pm_conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_pm_conversations` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Subject` text DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `Status` enum('Open','Unanswered','Resolved') DEFAULT NULL,
  `Level` int(11) DEFAULT NULL,
  `AssignedToUser` int(11) DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Unread` tinyint(1) DEFAULT NULL,
  `ResolverID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `StatusAssigned` (`Status`,`AssignedToUser`),
  KEY `StatusLevel` (`Status`,`Level`),
  KEY `spc_user_unr_idx` (`UserID`,`Unread`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_pm_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_pm_messages` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) DEFAULT NULL,
  `SentDate` datetime DEFAULT NULL,
  `Message` text DEFAULT NULL,
  `ConvID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `convid_idx` (`ConvID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_pm_responses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_pm_responses` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Message` text DEFAULT NULL,
  `Name` text DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stylesheets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stylesheets` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Default` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `default_idx` (`Default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tag_aliases`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tag_aliases` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `BadTag` varchar(100) NOT NULL,
  `AliasTag` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ta_bad_uidx` (`BadTag`),
  KEY `AliasTag` (`AliasTag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `TagType` enum('genre','other') NOT NULL DEFAULT 'other',
  `Uses` int(12) NOT NULL DEFAULT 1,
  `UserID` int(10) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name_2` (`Name`),
  KEY `TagType` (`TagType`),
  KEY `Uses` (`Uses`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thread`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thread` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `ThreadTypeID` int(6) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `ThreadTypeID` (`ThreadTypeID`),
  CONSTRAINT `thread_ibfk_1` FOREIGN KEY (`ThreadTypeID`) REFERENCES `thread_type` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thread_note`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thread_note` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `ThreadID` int(6) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `UserID` int(10) unsigned NOT NULL,
  `Body` mediumtext NOT NULL,
  `Visibility` enum('staff','public') NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `ThreadID` (`ThreadID`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `thread_note_ibfk_1` FOREIGN KEY (`ThreadID`) REFERENCES `thread` (`ID`),
  CONSTRAINT `thread_note_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thread_type`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thread_type` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(20) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `top10_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `top10_history` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Date` datetime NOT NULL DEFAULT current_timestamp(),
  `Type` enum('Daily','Weekly') DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `top10_history_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `top10_history_torrents` (
  `HistoryID` int(10) NOT NULL DEFAULT 0,
  `Rank` tinyint(2) NOT NULL DEFAULT 0,
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `TitleString` varchar(150) NOT NULL DEFAULT '',
  `TagString` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrent_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrent_attr` (
  `ID` int(6) NOT NULL AUTO_INCREMENT,
  `Name` varchar(24) NOT NULL,
  `Description` varchar(500) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrent_group_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrent_group_attr` (
  `ID` int(6) NOT NULL AUTO_INCREMENT,
  `Name` varchar(24) NOT NULL,
  `Description` varchar(500) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrent_group_has_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrent_group_has_attr` (
  `TorrentGroupID` int(10) NOT NULL,
  `TorrentGroupAttrID` int(6) NOT NULL,
  PRIMARY KEY (`TorrentGroupID`,`TorrentGroupAttrID`),
  KEY `TorrentGroupAttrID` (`TorrentGroupAttrID`),
  CONSTRAINT `torrent_group_has_attr_ibfk_1` FOREIGN KEY (`TorrentGroupAttrID`) REFERENCES `torrent_group_attr` (`ID`),
  CONSTRAINT `torrent_group_has_attr_ibfk_2` FOREIGN KEY (`TorrentGroupID`) REFERENCES `torrents_group` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrent_has_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrent_has_attr` (
  `TorrentID` int(10) NOT NULL,
  `TorrentAttrID` int(6) NOT NULL,
  PRIMARY KEY (`TorrentID`,`TorrentAttrID`),
  KEY `TorrentAttrID` (`TorrentAttrID`),
  CONSTRAINT `torrent_has_attr_ibfk_1` FOREIGN KEY (`TorrentAttrID`) REFERENCES `torrent_attr` (`ID`),
  CONSTRAINT `torrent_has_attr_ibfk_2` FOREIGN KEY (`TorrentID`) REFERENCES `torrents` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `LogScore` int(6) NOT NULL DEFAULT 0,
  `LogChecksum` enum('0','1') NOT NULL DEFAULT '1',
  `info_hash` blob NOT NULL,
  `FileCount` int(6) NOT NULL,
  `FileList` mediumtext NOT NULL,
  `FilePath` varchar(255) NOT NULL DEFAULT '',
  `Size` bigint(12) NOT NULL,
  `FreeTorrent` enum('0','1','2') NOT NULL DEFAULT '0',
  `FreeLeechType` enum('0','1','2','3','4','5','6','7') NOT NULL DEFAULT '0',
  `Time` datetime DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `LastReseedRequest` datetime DEFAULT NULL,
  `TranscodedFrom` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `InfoHash` (`info_hash`(40)),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`),
  KEY `Media` (`Media`),
  KEY `Format` (`Format`),
  KEY `Encoding` (`Encoding`),
  KEY `Time` (`Time`),
  KEY `FreeTorrent` (`FreeTorrent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_artists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_artists` (
  `GroupID` int(10) NOT NULL,
  `ArtistID` int(10) NOT NULL,
  `AliasID` int(10) NOT NULL,
  `UserID` int(10) unsigned NOT NULL DEFAULT 0,
  `Importance` enum('1','2','3','4','5','6','7') NOT NULL DEFAULT '1',
  PRIMARY KEY (`GroupID`,`ArtistID`,`Importance`),
  KEY `ArtistID` (`ArtistID`),
  KEY `AliasID` (`AliasID`),
  KEY `Importance` (`Importance`),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_bad_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_bad_files` (
  `TorrentID` int(11) NOT NULL DEFAULT 0,
  `UserID` int(11) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_bad_folders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_bad_folders` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_bad_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_bad_tags` (
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`),
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_cassette_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_cassette_approved` (
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_group`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_group` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `ArtistID` int(10) DEFAULT NULL,
  `CategoryID` int(3) DEFAULT NULL,
  `Name` varchar(300) DEFAULT NULL,
  `Year` int(4) DEFAULT NULL,
  `CatalogueNumber` varchar(80) DEFAULT NULL,
  `RecordLabel` varchar(80) DEFAULT NULL,
  `ReleaseType` tinyint(2) DEFAULT 21,
  `TagList` varchar(500) NOT NULL DEFAULT '',
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `RevisionID` int(12) DEFAULT NULL,
  `WikiBody` text NOT NULL,
  `WikiImage` varchar(255) NOT NULL,
  `VanityHouse` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `ArtistID` (`ArtistID`),
  KEY `CategoryID` (`CategoryID`),
  KEY `Name` (`Name`(255)),
  KEY `Year` (`Year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_leech_stats`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_leech_stats` (
  `TorrentID` int(10) NOT NULL,
  `Seeders` int(6) unsigned NOT NULL DEFAULT 0,
  `Leechers` int(6) unsigned NOT NULL DEFAULT 0,
  `Snatched` int(6) unsigned NOT NULL DEFAULT 0,
  `Balance` bigint(20) NOT NULL DEFAULT 0,
  `last_action` datetime DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `tls_seeders_idx` (`Seeders`),
  KEY `tls_snatched_idx` (`Snatched`),
  KEY `tls_last_action_idx` (`last_action`),
  CONSTRAINT `torrents_leech_stats_ibfk_1` FOREIGN KEY (`TorrentID`) REFERENCES `torrents` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_logs` (
  `LogID` int(10) NOT NULL AUTO_INCREMENT,
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `FileName` varchar(255) NOT NULL DEFAULT '',
  `Details` mediumtext NOT NULL,
  `Score` int(3) NOT NULL,
  `Checksum` enum('0','1') NOT NULL DEFAULT '1',
  `Adjusted` enum('0','1') NOT NULL DEFAULT '0',
  `AdjustedScore` int(3) NOT NULL,
  `AdjustedChecksum` enum('0','1') NOT NULL DEFAULT '0',
  `AdjustedBy` int(10) NOT NULL DEFAULT 0,
  `AdjustmentReason` text DEFAULT NULL,
  `AdjustmentDetails` text DEFAULT NULL,
  `Ripper` varchar(255) NOT NULL DEFAULT '',
  `RipperVersion` varchar(255) DEFAULT NULL,
  `Language` varchar(2) NOT NULL DEFAULT 'en',
  `ChecksumState` enum('checksum_ok','checksum_missing','checksum_invalid') NOT NULL DEFAULT 'checksum_ok',
  `LogcheckerVersion` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`LogID`),
  KEY `TorrentID` (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_lossymaster_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_lossymaster_approved` (
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_lossyweb_approved`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_lossyweb_approved` (
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_missing_lineage`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_missing_lineage` (
  `TorrentID` int(10) NOT NULL DEFAULT 0,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `TimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TorrentID`),
  KEY `TimeAdded` (`TimeAdded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_peerlists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_peerlists` (
  `TorrentID` int(11) NOT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `Seeders` int(11) DEFAULT NULL,
  `Leechers` int(11) DEFAULT NULL,
  `Snatches` int(11) DEFAULT NULL,
  PRIMARY KEY (`TorrentID`),
  KEY `Stats` (`TorrentID`,`Seeders`,`Leechers`,`Snatches`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_peerlists_compare`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_peerlists_compare` (
  `TorrentID` int(11) NOT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `Seeders` int(11) DEFAULT NULL,
  `Leechers` int(11) DEFAULT NULL,
  `Snatches` int(11) DEFAULT NULL,
  PRIMARY KEY (`TorrentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_tags` (
  `TagID` int(10) NOT NULL DEFAULT 0,
  `GroupID` int(10) NOT NULL DEFAULT 0,
  `PositiveVotes` int(6) NOT NULL DEFAULT 1,
  `NegativeVotes` int(6) NOT NULL DEFAULT 1,
  `UserID` int(10) DEFAULT NULL,
  PRIMARY KEY (`TagID`,`GroupID`),
  KEY `TagID` (`TagID`),
  KEY `GroupID` (`GroupID`),
  KEY `NegativeVotes` (`NegativeVotes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_tags_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_tags_votes` (
  `GroupID` int(10) NOT NULL,
  `TagID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `Way` enum('up','down') NOT NULL DEFAULT 'up',
  PRIMARY KEY (`GroupID`,`TagID`,`UserID`,`Way`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torrents_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `torrents_votes` (
  `GroupID` int(10) NOT NULL,
  `Ups` int(10) unsigned NOT NULL DEFAULT 0,
  `Total` int(10) unsigned NOT NULL DEFAULT 0,
  `Score` float NOT NULL DEFAULT 0,
  PRIMARY KEY (`GroupID`),
  KEY `Score` (`Score`),
  CONSTRAINT `torrents_votes_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `torrents_group` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_attr` (
  `ID` int(6) NOT NULL AUTO_INCREMENT,
  `Name` varchar(24) NOT NULL,
  `Description` varchar(500) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_bonus`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_bonus` (
  `user_id` int(10) unsigned NOT NULL,
  `points` float(20,5) NOT NULL DEFAULT 0.00000,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_bonus_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_flt`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_flt` (
  `user_id` int(10) unsigned NOT NULL,
  `tokens` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_flt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_has_attr`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_has_attr` (
  `UserID` int(10) unsigned NOT NULL,
  `UserAttrID` int(6) NOT NULL,
  PRIMARY KEY (`UserID`,`UserAttrID`),
  KEY `UserAttrID` (`UserAttrID`),
  CONSTRAINT `user_has_attr_ibfk_1` FOREIGN KEY (`UserAttrID`) REFERENCES `user_attr` (`ID`),
  CONSTRAINT `user_has_attr_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_last_access`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_last_access` (
  `user_id` int(10) unsigned NOT NULL,
  `last_access` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `ula_la_idx` (`last_access`),
  CONSTRAINT `user_last_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_torrent_remove`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_torrent_remove` (
  `user_id` int(10) unsigned NOT NULL,
  `torrent_id` int(10) NOT NULL,
  `removed` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`torrent_id`),
  KEY `utr_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_collage_subs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_collage_subs` (
  `UserID` int(10) NOT NULL,
  `CollageID` int(10) NOT NULL,
  `LastVisit` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`UserID`,`CollageID`),
  KEY `CollageID` (`CollageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_comments_last_read`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_comments_last_read` (
  `UserID` int(10) NOT NULL,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`Page`,`PageID`),
  KEY `Page` (`Page`,`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_donor_ranks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_donor_ranks` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Rank` tinyint(2) NOT NULL DEFAULT 0,
  `DonationTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Hidden` tinyint(2) NOT NULL DEFAULT 0,
  `TotalRank` int(10) NOT NULL DEFAULT 0,
  `SpecialRank` tinyint(2) DEFAULT 0,
  `InvitesReceivedRank` tinyint(2) DEFAULT 0,
  `RankExpirationTime` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`UserID`),
  KEY `DonationTime` (`DonationTime`),
  KEY `SpecialRank` (`SpecialRank`),
  KEY `Rank` (`Rank`),
  KEY `TotalRank` (`TotalRank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_downloads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_downloads` (
  `UserID` int(10) NOT NULL,
  `TorrentID` int(1) NOT NULL,
  `Time` datetime NOT NULL,
  PRIMARY KEY (`UserID`,`TorrentID`,`Time`),
  KEY `TorrentID` (`TorrentID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_dupes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_dupes` (
  `GroupID` int(10) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `UserID` (`UserID`),
  KEY `GroupID` (`GroupID`),
  CONSTRAINT `users_dupes_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `users_dupes_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `dupe_groups` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_enable_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_freeleeches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_freeleeches` (
  `UserID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `Time` datetime NOT NULL,
  `Expired` tinyint(1) NOT NULL DEFAULT 0,
  `Downloaded` bigint(20) NOT NULL DEFAULT 0,
  `Uses` int(10) NOT NULL DEFAULT 1,
  PRIMARY KEY (`UserID`,`TorrentID`),
  KEY `Expired_Time` (`Expired`,`Time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_geodistribution`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_geodistribution` (
  `Code` varchar(2) NOT NULL,
  `Users` int(10) NOT NULL,
  PRIMARY KEY (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_history_emails`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_history_emails` (
  `UserID` int(10) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `IP` varchar(15) DEFAULT NULL,
  `users_history_emails_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`users_history_emails_id`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_history_ips`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_history_ips` (
  `UserID` int(10) NOT NULL,
  `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `StartTime` datetime NOT NULL DEFAULT current_timestamp(),
  `EndTime` datetime DEFAULT NULL,
  PRIMARY KEY (`UserID`,`IP`,`StartTime`),
  KEY `UserID` (`UserID`),
  KEY `IP` (`IP`),
  KEY `EndTime` (`EndTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_history_passkeys`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_history_passkeys` (
  `UserID` int(10) NOT NULL,
  `OldPassKey` varchar(32) NOT NULL,
  `NewPassKey` varchar(32) NOT NULL,
  `ChangeTime` datetime NOT NULL DEFAULT current_timestamp(),
  `ChangerIP` varchar(15) NOT NULL,
  PRIMARY KEY (`UserID`,`OldPassKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_history_passwords`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_history_passwords` (
  `UserID` int(10) NOT NULL,
  `ChangeTime` datetime DEFAULT NULL,
  `ChangerIP` varchar(15) DEFAULT NULL,
  KEY `User_Time` (`UserID`,`ChangeTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_info`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_info` (
  `UserID` int(10) unsigned NOT NULL,
  `StyleID` int(10) unsigned NOT NULL,
  `StyleURL` varchar(255) DEFAULT NULL,
  `Info` text NOT NULL DEFAULT '',
  `Avatar` varchar(255) NOT NULL DEFAULT '',
  `AdminComment` text NOT NULL DEFAULT '',
  `SiteOptions` text NOT NULL DEFAULT '',
  `ViewAvatars` enum('0','1') NOT NULL DEFAULT '1',
  `Donor` enum('0','1') NOT NULL DEFAULT '0',
  `Artist` enum('0','1') NOT NULL DEFAULT '0',
  `DownloadAlt` enum('0','1') NOT NULL DEFAULT '0',
  `Warned` datetime DEFAULT NULL,
  `SupportFor` varchar(255) NOT NULL DEFAULT '',
  `TorrentGrouping` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '0=Open,1=Closed,2=Off',
  `ShowTags` enum('0','1') NOT NULL DEFAULT '1',
  `NotifyOnQuote` enum('0','1','2') NOT NULL DEFAULT '0',
  `AuthKey` varchar(32) NOT NULL,
  `ResetKey` varchar(32) NOT NULL DEFAULT '',
  `ResetExpires` datetime DEFAULT NULL,
  `JoinDate` datetime DEFAULT current_timestamp(),
  `Inviter` int(10) DEFAULT NULL,
  `BitcoinAddress` varchar(34) DEFAULT NULL,
  `WarnedTimes` int(2) NOT NULL DEFAULT 0,
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
  `RatioWatchEnds` datetime DEFAULT NULL,
  `RatioWatchDownload` bigint(20) unsigned NOT NULL DEFAULT 0,
  `RatioWatchTimes` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `BanDate` datetime DEFAULT NULL,
  `BanReason` enum('0','1','2','3','4') NOT NULL DEFAULT '0',
  `CatchupTime` datetime DEFAULT NULL,
  `LastReadNews` int(10) NOT NULL DEFAULT 0,
  `HideCountryChanges` enum('0','1') NOT NULL DEFAULT '0',
  `RestrictedForums` varchar(150) NOT NULL DEFAULT '',
  `DisableRequests` enum('0','1') NOT NULL DEFAULT '0',
  `PermittedForums` varchar(150) NOT NULL DEFAULT '',
  `UnseededAlerts` enum('0','1') NOT NULL DEFAULT '0',
  `LastReadBlog` int(10) NOT NULL DEFAULT 0,
  `InfoTitle` varchar(255) NOT NULL DEFAULT '',
  `NotifyOnDeleteSeeding` enum('0','1') NOT NULL DEFAULT '1',
  `NotifyOnDeleteSnatched` enum('0','1') NOT NULL DEFAULT '1',
  `NotifyOnDeleteDownloaded` enum('0','1') NOT NULL DEFAULT '1',
  `NavItems` varchar(255) NOT NULL DEFAULT '',
  `collages` int(6) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `UserID` (`UserID`),
  KEY `SupportFor` (`SupportFor`),
  KEY `Donor` (`Donor`),
  KEY `Warned` (`Warned`),
  KEY `JoinDate` (`JoinDate`),
  KEY `Inviter` (`Inviter`),
  KEY `RatioWatchEnds` (`RatioWatchEnds`),
  KEY `ResetKey` (`ResetKey`),
  KEY `ui_bandate_idx` (`BanDate`),
  CONSTRAINT `users_info_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_leech_stats`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_leech_stats` (
  `UserID` int(10) unsigned NOT NULL,
  `Uploaded` bigint(20) unsigned NOT NULL DEFAULT 0,
  `Downloaded` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`),
  KEY `uls_downloaded_idx` (`Downloaded`),
  CONSTRAINT `users_leech_stats_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_levels`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_levels` (
  `UserID` int(10) unsigned NOT NULL,
  `PermissionID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`UserID`,`PermissionID`),
  KEY `PermissionID` (`PermissionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_main`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_main` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Username` varchar(20) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `PassHash` varchar(60) NOT NULL,
  `IRCKey` char(32) DEFAULT NULL,
  `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `Class` tinyint(2) NOT NULL DEFAULT 5,
  `title` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `Enabled` enum('0','1','2','unconfirmed','enabled','disabled','banned') NOT NULL DEFAULT '0',
  `Paranoia` text DEFAULT NULL,
  `Visible` enum('0','1','yes','no') NOT NULL DEFAULT '1',
  `Invites` int(10) unsigned NOT NULL DEFAULT 0,
  `PermissionID` int(10) unsigned NOT NULL,
  `CustomPermissions` text DEFAULT NULL,
  `can_leech` tinyint(4) NOT NULL DEFAULT 1,
  `torrent_pass` char(32) NOT NULL,
  `RequiredRatio` double NOT NULL DEFAULT 0,
  `ipcc` varchar(2) NOT NULL DEFAULT '',
  `2FA_Key` varchar(16) DEFAULT NULL,
  `Recovery` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Username` (`Username`),
  KEY `Email` (`Email`),
  KEY `Class` (`Class`),
  KEY `Enabled` (`Enabled`),
  KEY `torrent_pass` (`torrent_pass`),
  KEY `RequiredRatio` (`RequiredRatio`),
  KEY `cc_index` (`ipcc`),
  KEY `PermissionID` (`PermissionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_notifications_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_notifications_settings` (
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Inbox` tinyint(1) DEFAULT 1,
  `StaffPM` tinyint(1) DEFAULT 1,
  `News` tinyint(1) DEFAULT 1,
  `Blog` tinyint(1) DEFAULT 1,
  `Torrents` tinyint(1) DEFAULT 1,
  `Collages` tinyint(1) DEFAULT 1,
  `Quotes` tinyint(1) DEFAULT 1,
  `Subscriptions` tinyint(1) DEFAULT 1,
  `SiteAlerts` tinyint(1) DEFAULT 1,
  `RequestAlerts` tinyint(1) DEFAULT 1,
  `CollageAlerts` tinyint(1) DEFAULT 1,
  `TorrentAlerts` tinyint(1) DEFAULT 1,
  `ForumAlerts` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_notify_filters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `FromYear` int(4) NOT NULL DEFAULT 0,
  `ToYear` int(4) NOT NULL DEFAULT 0,
  `ExcludeVA` enum('1','0') NOT NULL DEFAULT '0',
  `NewGroupsOnly` enum('1','0') NOT NULL DEFAULT '0',
  `ReleaseTypes` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `FromYear` (`FromYear`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_notify_quoted`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_notify_quoted` (
  `UserID` int(10) NOT NULL,
  `QuoterID` int(10) NOT NULL,
  `Page` enum('forums','artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  `PostID` int(10) NOT NULL,
  `UnRead` tinyint(1) NOT NULL DEFAULT 1,
  `Date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`UserID`,`Page`,`PostID`),
  KEY `page_pageid_idx` (`Page`,`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_notify_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_notify_torrents` (
  `UserID` int(10) NOT NULL,
  `FilterID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UnRead` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`UserID`,`TorrentID`),
  KEY `TorrentID` (`TorrentID`),
  KEY `UserID_Unread` (`UserID`,`UnRead`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_push_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_push_notifications` (
  `UserID` int(10) NOT NULL,
  `PushService` tinyint(1) NOT NULL DEFAULT 0,
  `PushOptions` text NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_sessions` (
  `UserID` int(10) NOT NULL,
  `SessionID` char(32) NOT NULL,
  `KeepLogged` enum('0','1') NOT NULL DEFAULT '0',
  `Browser` varchar(40) DEFAULT NULL,
  `OperatingSystem` varchar(13) DEFAULT NULL,
  `IP` varchar(15) NOT NULL,
  `LastUpdate` datetime NOT NULL,
  `Active` tinyint(4) NOT NULL DEFAULT 1,
  `FullUA` text DEFAULT NULL,
  `BrowserVersion` varchar(40) DEFAULT NULL,
  `OperatingSystemVersion` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`UserID`,`SessionID`),
  KEY `UserID` (`UserID`),
  KEY `Active` (`Active`),
  KEY `ActiveAgeKeep` (`Active`,`LastUpdate`,`KeepLogged`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_stats_daily`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_stats_daily` (
  `UserID` int(10) unsigned NOT NULL,
  `Time` timestamp NOT NULL DEFAULT current_timestamp(),
  `Uploaded` bigint(20) NOT NULL DEFAULT 0,
  `Downloaded` bigint(20) NOT NULL DEFAULT 0,
  `BonusPoints` float(20,5) NOT NULL DEFAULT 0.00000,
  `Torrents` int(11) NOT NULL DEFAULT 0,
  `PerfectFLACs` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`Time`),
  CONSTRAINT `users_stats_daily_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_stats_monthly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_stats_monthly` (
  `UserID` int(10) unsigned NOT NULL,
  `Time` timestamp NOT NULL DEFAULT current_timestamp(),
  `Uploaded` bigint(20) NOT NULL DEFAULT 0,
  `Downloaded` bigint(20) NOT NULL DEFAULT 0,
  `BonusPoints` float(20,5) NOT NULL DEFAULT 0.00000,
  `Torrents` int(11) NOT NULL DEFAULT 0,
  `PerfectFLACs` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`Time`),
  CONSTRAINT `users_stats_monthly_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_stats_yearly`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_stats_yearly` (
  `UserID` int(10) unsigned NOT NULL,
  `Time` timestamp NOT NULL DEFAULT current_timestamp(),
  `Uploaded` bigint(20) NOT NULL DEFAULT 0,
  `Downloaded` bigint(20) NOT NULL DEFAULT 0,
  `BonusPoints` float(20,5) NOT NULL DEFAULT 0.00000,
  `Torrents` int(11) NOT NULL DEFAULT 0,
  `PerfectFLACs` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`Time`),
  CONSTRAINT `users_stats_yearly_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_subscriptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_subscriptions` (
  `UserID` int(10) NOT NULL,
  `TopicID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`TopicID`),
  KEY `us_topicid_idx` (`TopicID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_subscriptions_comments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_subscriptions_comments` (
  `UserID` int(10) NOT NULL,
  `Page` enum('artist','collages','requests','torrents') NOT NULL,
  `PageID` int(10) NOT NULL,
  PRIMARY KEY (`UserID`,`Page`,`PageID`),
  KEY `usc_pageid_idx` (`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_summary`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_summary` (
  `UserID` int(10) unsigned NOT NULL,
  `Groups` int(10) NOT NULL DEFAULT 0,
  `PerfectFlacs` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`),
  CONSTRAINT `users_summary_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_torrent_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_torrent_history` (
  `UserID` int(10) unsigned NOT NULL,
  `NumTorrents` int(6) unsigned NOT NULL,
  `Date` int(8) unsigned NOT NULL,
  `Time` int(11) unsigned NOT NULL DEFAULT 0,
  `LastTime` int(11) unsigned NOT NULL DEFAULT 0,
  `Finished` enum('1','0') NOT NULL DEFAULT '1',
  `Weight` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`UserID`,`NumTorrents`,`Date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_votes` (
  `UserID` int(10) unsigned NOT NULL,
  `GroupID` int(10) NOT NULL,
  `Type` enum('Up','Down') DEFAULT NULL,
  `Time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`UserID`,`GroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `Vote` (`Type`,`GroupID`,`UserID`),
  CONSTRAINT `users_votes_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `torrents_group` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `users_votes_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_warnings_forums`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_warnings_forums` (
  `UserID` int(10) unsigned NOT NULL,
  `Comment` text NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_aliases`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki_aliases` (
  `Alias` varchar(50) NOT NULL,
  `UserID` int(10) NOT NULL,
  `ArticleID` int(10) DEFAULT NULL,
  PRIMARY KEY (`Alias`),
  KEY `article_idx` (`ArticleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_articles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki_articles` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `Revision` int(10) NOT NULL DEFAULT 1,
  `Title` varchar(100) DEFAULT NULL,
  `Body` mediumtext DEFAULT NULL,
  `MinClassRead` int(4) DEFAULT NULL,
  `MinClassEdit` int(4) DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Author` int(10) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_artists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki_artists` (
  `RevisionID` int(12) NOT NULL AUTO_INCREMENT,
  `PageID` int(10) NOT NULL DEFAULT 0,
  `Body` text DEFAULT NULL,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Summary` varchar(100) DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`RevisionID`),
  KEY `PageID` (`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_revisions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki_revisions` (
  `ID` int(10) NOT NULL,
  `Revision` int(10) NOT NULL,
  `Title` varchar(100) DEFAULT NULL,
  `Body` mediumtext DEFAULT NULL,
  `Date` datetime DEFAULT NULL,
  `Author` int(10) DEFAULT NULL,
  KEY `ID_Revision` (`ID`,`Revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_torrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wiki_torrents` (
  `RevisionID` int(12) NOT NULL AUTO_INCREMENT,
  `PageID` int(10) NOT NULL DEFAULT 0,
  `Body` text DEFAULT NULL,
  `UserID` int(10) NOT NULL DEFAULT 0,
  `Summary` varchar(100) DEFAULT NULL,
  `Time` datetime NOT NULL DEFAULT current_timestamp(),
  `Image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`RevisionID`),
  KEY `PageID` (`PageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xbt_client_whitelist`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xbt_client_whitelist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `peer_id` varchar(20) DEFAULT NULL,
  `vstring` varchar(200) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `peer_id` (`peer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xbt_files_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xbt_files_history` (
  `uid` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `seedtime` int(11) NOT NULL DEFAULT 0,
  `downloaded` bigint(20) NOT NULL DEFAULT 0,
  `uploaded` bigint(20) NOT NULL DEFAULT 0,
  UNIQUE KEY `xfh_uid_fid_idx` (`uid`,`fid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xbt_files_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xbt_files_users` (
  `uid` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `announced` int(11) NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `downloaded` bigint(20) NOT NULL DEFAULT 0,
  `remaining` bigint(20) NOT NULL DEFAULT 0,
  `uploaded` bigint(20) NOT NULL DEFAULT 0,
  `upspeed` int(10) unsigned NOT NULL DEFAULT 0,
  `downspeed` int(10) unsigned NOT NULL DEFAULT 0,
  `corrupt` bigint(20) NOT NULL DEFAULT 0,
  `timespent` int(10) unsigned NOT NULL DEFAULT 0,
  `useragent` varchar(51) NOT NULL DEFAULT '',
  `connectable` tinyint(4) NOT NULL DEFAULT 1,
  `peer_id` binary(20) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `fid` int(11) NOT NULL,
  `mtime` int(11) NOT NULL DEFAULT 0,
  `ip` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`peer_id`,`fid`,`uid`),
  KEY `fid_idx` (`fid`),
  KEY `mtime_idx` (`mtime`),
  KEY `uid_active_remain_mtime_idx` (`uid`,`active`,`remaining`,`mtime`),
  KEY `remain_mtime_idx` (`remaining`,`mtime`),
  KEY `xfu_uid_idx` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xbt_forex`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xbt_forex` (
  `btc_forex_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cc` enum('EUR','USD') NOT NULL DEFAULT 'USD',
  `rate` float(24,12) NOT NULL,
  `forex_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`btc_forex_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `xbt_snatched`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xbt_snatched` (
  `uid` int(11) NOT NULL DEFAULT 0,
  `tstamp` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `IP` varchar(15) NOT NULL,
  `seedtime` int(11) NOT NULL DEFAULT 0,
  KEY `fid` (`fid`),
  KEY `uid_tstamp` (`uid`,`tstamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-10-11 21:12:19
