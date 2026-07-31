<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropBookmark extends AbstractMigration {
    public function up(): void {
        foreach (['artists', 'collages', 'requests', 'torrents'] as $table) {
            $this->table("bookmarks_{$table}")->drop()->save();
        }
    }

    public function down(): void {
        $this->query('
            CREATE TABLE bookmarks_artists (
                UserID int NOT NULL,
                ArtistID int NOT NULL,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (ArtistID, UserID),
                KEY UserID (UserID)
            )
        ');

        $this->query('
            CREATE TABLE bookmarks_collages (
                UserID int NOT NULL,
                CollageID int NOT NULL,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (CollageID, UserID),
                KEY UserID (UserID),
                FOREIGN KEY (CollageID) REFERENCES collages (ID) ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (UserID) REFERENCES users_main (ID)
            )
        ');

        $this->query('
            CREATE TABLE bookmarks_requests (
                UserID int NOT NULL,
                RequestID int NOT NULL,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (RequestID, UserID),
                KEY UserID (UserID)
            )
        ');

        $this->query('
            CREATE TABLE bookmarks_torrents (
                UserID int NOT NULL,
                GroupID int NOT NULL,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                Sort int NOT NULL DEFAULT 0,
                PRIMARY KEY (UserID, GroupID),
                KEY GroupID (GroupID),
                KEY GroupID_2 (GroupID),
                FOREIGN KEY (GroupID) REFERENCES torrents_group (ID),
                FOREIGN KEY (UserID) REFERENCES users_main (ID)
            )
        ');
    }
}
