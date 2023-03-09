<?php

use Phinx\Migration\AbstractMigration;

class UnicodeDiscogsArtists extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE artist_discogs
            MODIFY `stem` varchar(100) CHARACTER SET utf8mb4 NOT NULL COLLATE utf8mb4_bin,
            MODIFY `name` varchar(100) CHARACTER SET utf8mb4 NOT NULL COLLATE utf8mb4_bin,
            ADD KEY /* IF NOT EXISTS */ ad_stem_idx (stem)
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE artist_discogs
            MODIFY `stem` varchar(100) CHARACTER SET utf8mb4 NOT NULL,
            MODIFY `name` varchar(100) CHARACTER SET utf8mb4 NOT NULL,
            DROP KEY /* IF EXISTS */ ad_stem_idx
        ");
    }
}
