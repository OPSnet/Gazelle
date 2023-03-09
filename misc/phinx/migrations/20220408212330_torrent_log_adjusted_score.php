<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TorrentLogAdjustedScore extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_logs')
            ->changeColumn('AdjustedScore', 'integer', [
                'null' => true,
            ])
            ->save();

    }

    public function down(): void {
        $this->table('torrents_logs')
            ->changeColumn('AdjustedScore', 'integer', [
                'null' => false,
                'limit' => '3',
            ])
            ->save();
    }
}
