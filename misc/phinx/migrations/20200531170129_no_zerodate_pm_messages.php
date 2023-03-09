<?php

use Phinx\Migration\AbstractMigration;

class NoZerodatePmMessages extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE pm_messages
            MODIFY SentDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE pm_messages
            MODIFY SentDate datetime
        ");
    }
}
