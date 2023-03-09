<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateTorrentsGroup extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE torrents_group
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE torrents_group
            MODIFY Time datetime
        ");
    }
}
