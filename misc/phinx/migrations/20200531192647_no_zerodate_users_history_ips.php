<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersHistoryIps extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL
        ");
    }
}
