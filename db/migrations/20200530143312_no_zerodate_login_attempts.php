<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateLoginAttempts extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE login_attempts
            MODIFY LastAttempt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY BannedUntil datetime DEFAULT NULL,
            MODIFY Attempts int(10) unsigned NOT NULL DEFAULT 1
        ");
        $this->execute("UPDATE login_attempts SET LastAttempt = now() WHERE LastAttempt = '0000-00-00 00:00:00'");
        $this->execute("UPDATE login_attempts SET BannedUntil = NULL WHERE BannedUntil = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE login_attempts
            MODIFY LastAttempt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY BannedUntil datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY Attempts int(10) unsigned NOT NULL
        ");
    }
}
