<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForums extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime DEFAULT NULL
        ");
        $this->execute("UPDATE forums SET LastPostTime = NULL WHERE LastPostTime = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

