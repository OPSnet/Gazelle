<?php

use Phinx\Migration\AbstractMigration;

class ContestTypes extends AbstractMigration {
    public function up(): void {
        $this->execute("
            INSERT IGNORE INTO contest_type (Name)
            VALUES
                ('upload_flac'),
                ('request_fill'),
                ('upload_flac_no_single'),
                ('upload_perfect_flac')
        ");
    }

    public function down(): void {
    }
}
