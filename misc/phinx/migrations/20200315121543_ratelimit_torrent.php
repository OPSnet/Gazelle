<?php

use Phinx\Migration\AbstractMigration;

class RateLimitTorrent extends AbstractMigration
{
    public function up(): void {
        $this->table('ratelimit_torrent', ['id' => false, 'primary_key' => 'ratelimit_torrent_id'])
            ->addColumn('ratelimit_torrent_id', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('torrent_id', 'integer', ['limit' => 10])
            ->addColumn('logged', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->addForeignKey('torrent_id', 'torrents', 'ID')
            ->save();
    }

    public function down(): void {
        $this->table('ratelimit_torrent')->drop()->update();
    }
}
