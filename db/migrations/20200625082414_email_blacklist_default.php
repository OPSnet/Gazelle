<?php

use Phinx\Migration\AbstractMigration;

class EmailBlacklistDefault extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE email_blacklist
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE email_blacklist
            MODIFY Time datetime NOT NULL
        ");
    }
}
