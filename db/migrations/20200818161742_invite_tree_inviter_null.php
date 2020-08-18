<?php

use Phinx\Migration\AbstractMigration;

class InviteTreeInviterNull extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE invite_tree MODIFY InviterID int(10) DEFAULT NULL");
        $this->execute("UPDATE invite_tree SET InviterID = NULL WHERE InviterID = 0");
    }

    public function down() {
        $this->execute("UPDATE invite_tree SET InviterID = 0 WHERE InviterID IS NULL");
        $this->execute("ALTER TABLE invite_tree MODIFY InviterID int(10) NOT NULL DEFAULT 0");
    }
}
