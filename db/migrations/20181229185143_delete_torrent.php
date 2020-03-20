<?php


use Phinx\Migration\AbstractMigration;

class DeleteTorrent extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up() {
      $this->table('torrents')
        ->changeColumn('last_action', 'datetime', ['null' => true])
        ->save();

        $this->execute("
UPDATE torrents SET last_action = NULL WHERE last_action='0000-00-00 00:00:00';

CREATE TABLE `deleted_torrents` (
  `ID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `UserID` int(10),
  `Media` varchar(20),
  `Format` varchar(10),
  `Encoding` varchar(15),
  `Remastered` enum('0','1') NOT NULL,
  `RemasterYear` int(4),
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
  `Leechers` int(6) NOT NULL,
  `Seeders` int(6) NOT NULL,
  `last_action` datetime,
  `FreeTorrent` enum('0','1','2') NOT NULL,
  `FreeLeechType` enum('0','1','2','3','4','5','6','7') NOT NULL,
  `Time` datetime NOT NULL,
  `Description` text,
  `Snatched` int(10) unsigned NOT NULL,
  `balance` bigint(20) NOT NULL,
  `LastReseedRequest` datetime NOT NULL,
  `TranscodedFrom` int(10) NOT NULL,
  PRIMARY KEY (`ID`)
);

CREATE TABLE `deleted_users_notify_torrents` (
  `UserID` int(10) NOT NULL,
  `FilterID` int(10) NOT NULL,
  `GroupID` int(10) NOT NULL,
  `TorrentID` int(10) NOT NULL,
  `UnRead` tinyint(4) NOT NULL,
  PRIMARY KEY (`UserID`,`TorrentID`)
);

CREATE TABLE `deleted_torrents_files` (
  `TorrentID` int(10) NOT NULL,
  `File` mediumblob NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_bad_files` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_bad_folders` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_bad_tags` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_cassette_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_lossymaster_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_lossyweb_approved` (
  `TorrentID` int(10) NOT NULL,
  `UserID` int(10) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

CREATE TABLE `deleted_torrents_missing_lineage` (
  `TorrentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `TimeAdded` datetime NOT NULL,
  PRIMARY KEY (`TorrentID`)
);

        ");
    }

    public function down() {
        $this->execute("
DROP TABLE `deleted_torrents`;
DROP TABLE `deleted_users_notify_torrents`;
DROP TABLE `deleted_torrents_files`;
DROP TABLE `deleted_torrents_bad_files`;
DROP TABLE `deleted_torrents_bad_folders`;
DROP TABLE `deleted_torrents_bad_tags`;
DROP TABLE `deleted_torrents_cassette_approved`;
DROP TABLE `deleted_torrents_lossymaster_approved`;
DROP TABLE `deleted_torrents_lossyweb_approved`;
DROP TABLE `deleted_torrents_missing_lineage`;
        ");
    }
}
