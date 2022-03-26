<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForums extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime DEFAULT NULL
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime
        ");
    }
}

