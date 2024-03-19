<?php

use Phinx\Migration\AbstractMigration;

class ZeroAttemptsLoginAttempts extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE login_attempts
            MODIFY Attempts int(10) NOT NULL DEFAULT 0
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE login_attempts
            MODIFY Attempts int(10) unsigned NOT NULL DEFAULT 1
        ");
    }
}
