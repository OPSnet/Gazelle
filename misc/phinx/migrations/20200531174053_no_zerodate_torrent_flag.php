<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateTorrentFlag extends AbstractMigration {
    protected function torrentTables(): array {
        return explode(" ", "torrents_bad_files torrents_bad_tags torrents_cassette_approved torrents_lossymaster_approved torrents_lossyweb_approved torrents_missing_lineage");
    }

    public function up(): void {
        foreach ($this->torrentTables() as $table) {
            $this->execute("ALTER TABLE $table
                MODIFY TimeAdded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
            ");
        }
    }

    public function down(): void {
        foreach ($this->torrentTables() as $table) {
            $this->execute("ALTER TABLE $table
                MODIFY TimeAdded datetime
            ");
        }
    }
}
