<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeletedTorrentTranscodedFrom extends AbstractMigration
{
    public function up(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('deleted_torrents')
            ->removeColumn('TranscodedFrom')
            ->save();
    }

    public function down(): void {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('deleted_torrents')
            ->addColumn('TranscodedFrom', 'integer', ['limit' => 10, 'default' => 0])
            ->save();
    }
}
