<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDownload extends AbstractMigration {
    public function up(): void {
        $this->table('user_download', ['id' => false, 'primary_key' => 'id_user_download'])
            ->addColumn('id_user_download', 'integer', ['identity' => true])
            ->addColumn('id_user', 'integer')
            ->addColumn('id_torrent', 'integer')
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['id_user'], ['name' => 'ud_u_idx'])
            ->addIndex(['id_torrent'], ['name' => 'ud_t_idx'])
            ->save();
    }

    public function down(): void {
        $this->table('user_download')->drop()->save();
    }
}
