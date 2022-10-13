<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateFeaturedAlbums extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE featured_albums
            MODIFY Started datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY Ended datetime DEFAULT NULL
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE featured_albums
            MODIFY Started datetime NOT NULL,
            MODIFY Ended datetime NOT NULL
        ");
    }
}

