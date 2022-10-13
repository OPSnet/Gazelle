<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateDonotupload extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE do_not_upload MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down() {
        $this->execute("ALTER TABLE do_not_upload MODIFY Time datetime NOT NULL");
    }
}
