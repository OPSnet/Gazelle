<?php

use Phinx\Migration\AbstractMigration;

class UserCollageSubsDefaultTime extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE users_collage_subs
            MODIFY LastVisit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE users_collage_subs
            MODIFY LastVisit datetime DEFAULT NULL
        ");
    }
}
