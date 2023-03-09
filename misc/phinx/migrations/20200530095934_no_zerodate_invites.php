<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateInvites extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE invites
            MODIFY Expires datetime
        ");
    }
}
