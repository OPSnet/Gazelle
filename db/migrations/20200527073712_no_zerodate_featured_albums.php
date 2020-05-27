<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateFeaturedAlbums extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE featured_albums
            MODIFY Started datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY Ended datetime DEFAULT NULL
        ");
        $this->execute("UPDATE featured_albums SET Ended = NULL WHERE Ended = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE featured_albums
            MODIFY Started datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY Ended datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

