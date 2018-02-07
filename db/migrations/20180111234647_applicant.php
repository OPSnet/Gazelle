<?php


use Phinx\Migration\AbstractMigration;

class Applicant extends AbstractMigration
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
    	$this->execute("
CREATE TABLE `thread_type` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(20) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
);

CREATE TABLE `thread` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `ThreadTypeID` int(6) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `thread_fk_type` (`ThreadTypeID`),
  CONSTRAINT `thread_fk_type` FOREIGN KEY (`ThreadTypeID`) REFERENCES `thread_type` (`ID`)
);

CREATE TABLE `thread_note` (
  `ID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `ThreadID` int(6) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `UserID` int(10) unsigned NOT NULL,
  `Body` mediumtext COLLATE utf8_swedish_ci NOT NULL,
  `Visibility` enum('staff','public') COLLATE utf8_swedish_ci NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `thread_note_fk_thread` (`ThreadID`),
  KEY `thread_note_fk_users_main` (`UserID`),
  CONSTRAINT `thread_note_fk_thread` FOREIGN KEY (`ThreadID`) REFERENCES `thread` (`ID`),
  CONSTRAINT `thread_note_fk_users_main` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
);

CREATE TABLE `applicant_role` (
  `ID` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `Title` varchar(40) COLLATE utf8_swedish_ci NOT NULL,
  `Published` tinyint(1) DEFAULT '0',
  `Description` text COLLATE utf8_swedish_ci,
  `UserID` int(10) unsigned NOT NULL,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `applicant_role_fk_user` (`UserID`),
  CONSTRAINT `applicant_role_fk_user` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
);

CREATE TABLE `applicant` (
  `ID` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `RoleID` int(4) unsigned NOT NULL,
  `UserID` int(10) unsigned NOT NULL,
  `ThreadID` int(6) unsigned NOT NULL,
  `Body` text NOT NULL,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Resolved` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `applicant_fk_role` (`RoleID`),
  KEY `applicant_fk_user` (`UserID`),
  KEY `applicant_fk_thread` (`ThreadID`),
  KEY `appl_resolved_idx` (`Resolved`),
  CONSTRAINT `applicant_fk_role` FOREIGN KEY (`RoleID`) REFERENCES `applicant_role` (`ID`),
  CONSTRAINT `applicant_fk_thread` FOREIGN KEY (`ThreadID`) REFERENCES `thread` (`ID`),
  CONSTRAINT `applicant_fk_user` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`)
);

INSERT INTO thread_type (Name) VALUES
    ('staff-pm'),
    ('staff-role'),
    ('torrent-report');
");
    }
}
