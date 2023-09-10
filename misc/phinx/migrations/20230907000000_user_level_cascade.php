<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserLevelCascade extends AbstractMigration {
    public function up(): void {
        $this->table('users_levels')
            ->addForeignKey('UserID',       'users_main',  'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('PermissionID', 'permissions', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('users_levels')
            ->dropForeignKey('UserID')
            ->dropForeignKey('PermissionID')
            ->save();
    }
}
