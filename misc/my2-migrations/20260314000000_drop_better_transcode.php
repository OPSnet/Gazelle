<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropBetterTranscode extends AbstractMigration {
    public function up(): void {
        $this->table('better_transcode_music')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE better_transcode_music (
                tgroup_id int NOT NULL,
                want_v0 tinyint NOT NULL,
                want_320 tinyint NOT NULL,
                edition varchar(255) NOT NULL,
                PRIMARY KEY (tgroup_id, edition)
            )
        ");
    }
}
