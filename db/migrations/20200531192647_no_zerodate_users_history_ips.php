<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersHistoryIps extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE users_history_ips SET StartTime = now() WHERE StartTime = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE users_history_ips
            MODIFY StartTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
