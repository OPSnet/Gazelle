<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersTop10History extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE top10_history
            MODIFY Date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE top10_history
            MODIFY Date datetime
        ");
    }
}
