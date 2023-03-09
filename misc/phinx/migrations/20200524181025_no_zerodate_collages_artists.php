<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateCollagesArtists extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE collages_artists MODIFY AddedOn datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down(): void {
        $this->execute("ALTER TABLE collages_artists MODIFY AddedOn datetime NOT NULL");
    }
}
