<?php

use Phinx\Migration\AbstractMigration;

class EmailBlacklistDefault extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE email_blacklist
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE email_blacklist
            MODIFY Time datetime NOT NULL
        ");
    }
}
