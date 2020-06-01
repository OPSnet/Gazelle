<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateNews extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE news SET Time = now() WHERE Time = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
