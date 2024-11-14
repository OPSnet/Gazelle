<?php

use Phinx\Migration\AbstractMigration;

class ArtistNameSort extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE torrents_artists
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (GroupID, Importance, ArtistID)
        ');
    }

    public function down(): void {
        $this->execute('
            ALTER TABLE torrents_artists
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (GroupID, ArtistID, Importance)
        ');
    }
}
