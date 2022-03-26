<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateNews extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE news
            MODIFY Time datetime
        ");
    }
}
