<?php

use Phinx\Migration\AbstractMigration;

class UserLastAccess extends AbstractMigration
{
    public function up(): void {
        $this->table('user_last_access', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('last_access', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['last_access'], ['name' => 'ula_la_idx'])
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
    }

    public function down(): void {
        $this->table('user_last_access')->drop()->update();
    }
}
