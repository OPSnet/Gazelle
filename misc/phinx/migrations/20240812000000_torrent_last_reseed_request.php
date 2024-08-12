<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TorrentLastReseedRequest extends AbstractMigration {
    public function up(): void {
        $this->table('torrents')->addIndex('LastReseedRequest', ['name' => 't_lrr_idx'])->save();
    }

    public function down(): void {
        $this->table('torrents')->removeIndex('LastReseedRequest')->save();
    }
}
