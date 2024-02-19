<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TopTenHistory extends AbstractMigration {
    public function up(): void {
        $this->table('top10_history_torrents')
            ->removeColumn('TagString')
            ->removeColumn('TitleString')
            ->save();
    }

    public function down(): void {
        $this->table('top10_history_torrents')
            ->addColumn('TagString', 'string', ['length' => 100])
            ->addColumn('TitleString', 'string', ['length' => 400])
            ->save();
    }
}
