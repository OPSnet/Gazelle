<?php

use Phinx\Migration\AbstractMigration;

class PermissionRateLimit extends AbstractMigration
{
    public function up(): void {
        $this->table('permission_rate_limit', ['id' => false, 'primary_key' => 'permission_id'])
            ->addColumn('permission_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('overshoot', 'integer')
            ->addColumn('factor', 'float')
            ->addForeignKey('permission_id', 'permissions', 'ID')
            ->save();

        $this->table('user_attr')
            ->insert([
                ['Name' => 'unlimited-download', 'Description' => 'This user can download an arbitrary number of torrent files']
            ])
            ->save();
    }

    public function down(): void {
        $this->table('permission_rate_limit')->drop()->update();
        $this->execute("DELETE FROM user_attr where Name = 'unlimited-download'");
    }
}
