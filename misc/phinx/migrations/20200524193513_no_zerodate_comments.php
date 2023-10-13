<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateComments extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE comments MODIFY AddedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down(): void {
        $this->execute("ALTER TABLE comments MODIFY AddedTime datetime NOT NULL");
    }
}
