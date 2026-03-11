<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropRequest extends AbstractMigration {
    public function up(): void {
        $this->table('requests_artists')->drop()->save();
        $this->table('requests_tags')->drop()->save();
        $this->table('requests_votes')->drop()->save();
        $this->table('requests')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE requests (
              ID int NOT NULL AUTO_INCREMENT,
              UserID int NOT NULL DEFAULT '0',
              TimeAdded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              LastVote datetime DEFAULT NULL,
              CategoryID int NOT NULL,
              Title varchar(255) DEFAULT NULL,
              Year int DEFAULT NULL,
              Image varchar(255) DEFAULT NULL,
              Description mediumtext NOT NULL,
              ReleaseType tinyint NOT NULL DEFAULT '21',
              CatalogueNumber varchar(50) NOT NULL,
              BitrateList varchar(255) DEFAULT NULL,
              FormatList varchar(255) DEFAULT NULL,
              MediaList varchar(255) DEFAULT NULL,
              LogCue varchar(20) DEFAULT NULL,
              FillerID int NOT NULL DEFAULT '0',
              TorrentID int NOT NULL DEFAULT '0',
              TimeFilled datetime DEFAULT NULL,
              Visible binary(1) NOT NULL DEFAULT '1',
              RecordLabel varchar(80) DEFAULT NULL,
              GroupID int DEFAULT NULL,
              OCLC varchar(55) DEFAULT '',
              Checksum tinyint(1) NOT NULL DEFAULT '0',
              created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (ID),
              KEY Userid (UserID),
              KEY Filled (TorrentID),
              KEY FillerID (FillerID),
              KEY TimeAdded (TimeAdded),
              KEY GroupID (GroupID)
            )
        ");
        $this->query("
            CREATE TABLE requests_artists (
              RequestID int NOT NULL,
              AliasID int NOT NULL,
              artist_role_id tinyint NOT NULL,
              UserID int DEFAULT NULL,
              created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (RequestID,AliasID,artist_role_id),
              KEY aliasid_idx (AliasID),
              KEY artist_role_id (artist_role_id),
              KEY UserID (UserID),
              KEY RequestID (RequestID),
              FOREIGN KEY (artist_role_id) REFERENCES artist_role (artist_role_id),
              FOREIGN KEY (UserID) REFERENCES users_main (ID) ON DELETE SET NULL ON UPDATE CASCADE,
              FOREIGN KEY (AliasID) REFERENCES artists_alias (AliasID)
            )
        ");
        $this->query("
            CREATE TABLE requests_tags (
              TagID int NOT NULL DEFAULT '0',
              RequestID int NOT NULL DEFAULT '0',
              PRIMARY KEY (TagID,RequestID),
              KEY TagID (TagID),
              KEY RequestID (RequestID)
            )
        ");
        $this->query("
            CREATE TABLE requests_votes (
              requests_votes_id int NOT NULL AUTO_INCREMENT,
              RequestID int NOT NULL DEFAULT '0',
              UserID int NOT NULL DEFAULT '0',
              Bounty bigint NOT NULL,
              created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (requests_votes_id),
              KEY RequestID (RequestID),
              KEY UserID (UserID),
              FOREIGN KEY (RequestID) REFERENCES requests (ID),
              FOREIGN KEY (UserID) REFERENCES users_main (ID)
            )
        ");
    }
}
