<?php

use Phinx\Migration\AbstractMigration;

class UserCollageSubsDefaultTime extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE users_collage_subs
            MODIFY LastVisit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE users_collage_subs
            MODIFY LastVisit datetime DEFAULT NULL
        ");
    }
}
