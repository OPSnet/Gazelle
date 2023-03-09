<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateBlog extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE blog MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down(): void {
        $this->execute("ALTER TABLE blog MODIFY Time datetime NOT NULL");
    }
}
