<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateChangelog extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE changelog MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down() {
        $this->execute("ALTER TABLE changelog MODIFY Time datetime NOT NULL");
    }
}
