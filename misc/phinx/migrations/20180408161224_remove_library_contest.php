<?php

use Phinx\Migration\AbstractMigration;

class RemoveLibraryContest extends AbstractMigration {
    public function up(): void {
        if ($this->table('library_contest')->exists()) {
            $this->table('library_contest')->drop()->update();
        }
    }

    public function down(): void {
    }
}
