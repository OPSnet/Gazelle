<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReportsNotesNull extends AbstractMigration {
    public function up(): void {
        $this->query("
            ALTER TABLE reports MODIFY COLUMN Notes mediumtext NULL
        ");
    }

    public function down(): void {
        $this->query("
            ALTER TABLE reports MODIFY COLUMN Notes mediumtext NOT NULL
        ");
    }
}
