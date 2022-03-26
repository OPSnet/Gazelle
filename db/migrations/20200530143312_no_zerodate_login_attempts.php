<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateLoginAttempts extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE login_attempts
            MODIFY LastAttempt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY BannedUntil datetime DEFAULT NULL,
            MODIFY Attempts int(10) unsigned NOT NULL DEFAULT 1
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE login_attempts
            MODIFY LastAttempt datetime,
            MODIFY BannedUntil datetime,
            MODIFY Attempts int(10) unsigned NOT NULL
        ");
    }
}
