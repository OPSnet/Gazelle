<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateInvites extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        // unlikely, but whatever
        $this->execute("UPDATE invites SET Expires = now() + INTERVAL 1 DAY WHERE Expires = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}
