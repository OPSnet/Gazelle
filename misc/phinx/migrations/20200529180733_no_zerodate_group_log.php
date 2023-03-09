<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateGroupLog extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE group_log
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE group_log
            MODIFY Time datetime
        ");
    }
}
