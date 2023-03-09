<?php

use Phinx\Migration\AbstractMigration;

class DeletedTorrentsAlter extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE deleted_torrents
            MODIFY Time datetime NULL,
            MODIFY Size bigint(12) NOT NULL");
    }

    public function down(): void {
        $this->execute("ALTER TABLE deleted_torrents
            MODIFY Time datetime NOT NULL,
            MODIFY Size bigint(20) NOT NULL");
    }
}
