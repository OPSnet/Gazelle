<?php

use Phinx\Migration\AbstractMigration;

class UserTorrentRemove extends AbstractMigration {
    public function change(): void {
        $this->table('user_torrent_remove', ['id' => false, 'primary_key' => ['torrent_id']])
            ->addColumn('user_id',    'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('torrent_id', 'integer',  ['limit' => 10])
            ->addColumn('removed',    'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'utr_user_idx'])
            ->create();
    }
}
