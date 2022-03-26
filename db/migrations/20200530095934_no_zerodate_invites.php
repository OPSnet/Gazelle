<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateInvites extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime
        ");
    }
}
