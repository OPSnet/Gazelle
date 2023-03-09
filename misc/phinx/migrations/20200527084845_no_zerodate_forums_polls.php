<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsPolls extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE forums_polls
            MODIFY Featured datetime DEFAULT NULL
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE forums_polls
            MODIFY Featured datetime NOT NULL
        ");
    }
}

