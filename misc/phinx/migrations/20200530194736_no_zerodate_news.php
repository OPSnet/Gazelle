<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateNews extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime
        ");
    }
}
