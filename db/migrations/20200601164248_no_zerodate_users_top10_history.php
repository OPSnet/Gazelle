<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateUsersTop10History extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE top10_history
            MODIFY Date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE top10_history
            MODIFY Date datetime
        ");
    }
}
