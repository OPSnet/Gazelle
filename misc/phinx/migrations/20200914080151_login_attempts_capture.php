<?php

use Phinx\Migration\AbstractMigration;

class LoginAttemptsCapture extends AbstractMigration {
    public function up(): void {
        $this->execute("
            ALTER TABLE login_attempts
                ADD COLUMN capture varchar(20) CHARACTER SET utf8mb4 DEFAULT NULL,
                MODIFY COLUMN UserID int(10) unsigned NOT NULL DEFAULT 0
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE login_attempts
                DROP COLUMN capture,
                MODIFY COLUMN UserID int(10) unsigned NOT NULL
        ");
    }
}
