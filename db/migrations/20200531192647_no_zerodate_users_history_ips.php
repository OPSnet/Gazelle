<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersHistoryIps extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL
        ");
    }
}
