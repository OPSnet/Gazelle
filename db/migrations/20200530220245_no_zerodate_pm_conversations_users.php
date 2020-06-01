<?php

use Phinx\Migration\AbstractMigration;

class NoZerodatePmConversationsUsers extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE pm_conversations_users
            MODIFY ReceivedDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY SentDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        // not sure how this could have happened
        $this->execute("UPDATE pm_conversations_users SET SentDate = ReceivedDate WHERE SentDate = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE pm_conversations_users
            MODIFY ReceivedDate datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY SentDate datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
