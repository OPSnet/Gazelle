<?php

use Phinx\Migration\AbstractMigration;

class NoZerodatePmMessages extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE pm_messages
            MODIFY SentDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE pm_messages
            MODIFY SentDate datetime
        ");
    }
}
