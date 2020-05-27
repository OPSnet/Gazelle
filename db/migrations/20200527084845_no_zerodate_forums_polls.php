<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsPolls extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums_polls
            MODIFY Featured datetime DEFAULT NULL
        ");
        $this->execute("UPDATE forums_polls SET Featured = NULL WHERE Featured = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE forums_polls
            MODIFY Featured datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

