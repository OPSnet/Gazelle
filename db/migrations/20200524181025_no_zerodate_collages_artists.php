<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateCollagesArtists extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE collages_artists MODIFY AddedOn datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down() {
        $this->execute("ALTER TABLE collages_artists MODIFY AddedOn datetime NOT NULL");
    }
}
