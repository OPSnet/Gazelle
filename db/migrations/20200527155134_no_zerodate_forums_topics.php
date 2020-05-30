<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsTopics extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE forums_topics SET LastPostTime = CreatedTime WHERE LastPostTime = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE forums_topics
            MODIFY LastPostTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

