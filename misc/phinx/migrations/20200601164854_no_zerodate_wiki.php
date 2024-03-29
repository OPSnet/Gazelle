<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateWiki extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE wiki_artists
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("ALTER TABLE wiki_torrents
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE wiki_artists
            MODIFY Time datetime
        ");
        $this->execute("ALTER TABLE wiki_torrents
            MODIFY Time datetime
        ");
    }
}
