<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class DropFileStorageTables extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_files')->drop()->update();
    }

    public function down(): void {
        $this->table('torrents_files', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'engine' => 'MyISAM',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => '10',
            ])
            ->addColumn('File', 'blob', [
                'null' => false,
                'limit' => MysqlAdapter::BLOB_MEDIUM,
            ])
            ->create();
    }
}
