<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropArtistsTags extends AbstractMigration {
    public function up(): void {
        $this->table('artists_tags')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE artists_tags (
                TagID int NOT NULL DEFAULT 0,
                ArtistID int NOT NULL DEFAULT 0,
                PositiveVotes int NOT NULL DEFAULT 1,
                NegativeVotes int NOT NULL DEFAULT 1,
                UserID int NOT NULL,
                PRIMARY KEY (TagID, ArtistID),
                KEY TagID (TagID, ArtistID, PositiveVotes, NegativeVotes, UserID)
            )
        ");
    }
}
