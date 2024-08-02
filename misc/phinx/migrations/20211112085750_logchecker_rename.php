<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LogcheckerRename extends AbstractMigration {
    public function up(): void {
        $this->execute("
            UPDATE nav_items SET
                title = 'Logchecker'
            WHERE tag = 'logchecker'
        ");
    }

    public function down(): void {
        $this->execute("
            UPDATE nav_items SET
                title = 'Log Checker'
            WHERE tag = 'logchecker'
        ");
    }
}
