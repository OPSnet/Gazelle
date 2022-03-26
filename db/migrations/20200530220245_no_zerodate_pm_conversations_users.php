<?php

use Phinx\Migration\AbstractMigration;

class NoZerodatePmConversationsUsers extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE pm_conversations_users
            MODIFY ReceivedDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY SentDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        // not sure how this could have happened
    }

    public function down() {
        $this->execute("ALTER TABLE pm_conversations_users
            MODIFY ReceivedDate datetime,
            MODIFY SentDate datetime
        ");
    }
}
