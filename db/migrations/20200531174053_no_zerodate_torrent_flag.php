<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateTorrentFlag extends AbstractMigration {
    protected function torrentTables() {
        return explode(" ", "torrents_bad_files torrents_bad_tags torrents_cassette_approved torrents_lossymaster_approved torrents_lossyweb_approved torrents_missing_lineage");
    }

    public function up() {
        foreach ($this->torrentTables() as $table) {
            echo "=> $table\n";
            $this->execute("ALTER TABLE $table
                MODIFY TimeAdded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
            ");
            $this->execute("UPDATE $table SET TimeAdded = now() WHERE TimeAdded = '0000-00-00 00:00:00'");
        }
    }

    public function down() {
        foreach ($this->torrentTables() as $table) {
            echo "=> $table\n";
            $this->execute("ALTER TABLE $table
                MODIFY Time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
            ");
        }
    }
}
