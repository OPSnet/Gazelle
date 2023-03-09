<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LongerTop10Title extends AbstractMigration {
    public function up(): void {
        $this->table('top10_history_torrents')
            ->changeColumn('TitleString', 'string', ['length' => 400, 'default' => ''])
            ->save();
    }

    public function down(): void {
        $this->table('top10_history_torrents')
            ->changeColumn('TitleString', 'string', ['length' => 150, 'default' => ''])
            ->save();
    }
}
