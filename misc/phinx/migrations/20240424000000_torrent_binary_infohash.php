<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class TorrentBinaryInfohash extends AbstractMigration {
    public function up(): void {
         $this->table('torrents')
            ->changeColumn('info_hash', 'binary', ['length' => 20])
            ->save();
         $this->table('deleted_torrents')
            ->changeColumn('info_hash', 'binary', ['length' => 20])
            ->save();
    }

    public function down(): void {
         $this->table('torrents')
            ->changeColumn('info_hash', 'blob')
            ->save();
         $this->table('deleted_torrents')
            ->changeColumn('info_hash', 'blob')
            ->save();
    }
}
