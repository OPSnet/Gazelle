<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersNotifyQuoted extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE users_notify_quoted
            MODIFY Date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE users_notify_quoted
            MODIFY Date datetime
        ");
    }
}
