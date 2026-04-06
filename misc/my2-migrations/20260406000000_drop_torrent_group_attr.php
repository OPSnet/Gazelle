<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropTorrentGroupAttr extends AbstractMigration {
    public function up(): void {
        $this->table('torrent_group_has_attr')->drop()->save();
        $this->table('torrent_group_attr')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE torrent_group_attr (
                ID int NOT NULL PRIMARY KEY AUTO_INCREMENT,
                Name varchar(24) NOT NULL,
                Description varchar(500) NOT NULL,
                UNIQUE KEY Name (Name)
            )
        ");
        $this->query("
            CREATE TABLE torrent_group_has_attr (
                TorrentGroupID int NOT NULL,
                TorrentGroupAttrID int NOT NULL,
                PRIMARY KEY (TorrentGroupID, TorrentGroupAttrID),
                KEY TorrentGroupAttrID (TorrentGroupAttrID),
                FOREIGN KEY (TorrentGroupAttrID) REFERENCES torrent_group_attr (ID),
                FOREIGN KEY (TorrentGroupID) REFERENCES torrents_group (ID)
            )
        ");
    }
}
