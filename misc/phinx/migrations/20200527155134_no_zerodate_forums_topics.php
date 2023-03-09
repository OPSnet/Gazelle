<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsTopics extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL
        ");
    }
}

