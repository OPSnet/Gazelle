<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsTopics extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL
        ");
    }
}

