<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateBlog extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE blog MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down() {
        $this->execute("ALTER TABLE blog MODIFY Time datetime NOT NULL");
    }
}
