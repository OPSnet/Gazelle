<?php

use Phinx\Migration\AbstractMigration;

class IpbanCreateDate extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE ip_bans
            ADD COLUMN user_id int(10) unsigned NOT NULL DEFAULT 0,
            ADD COLUMN created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE ip_bans
            DROP COLUMN user_id,
            DROP COLUMN created
        ");
    }
}

