<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserLinkForeignKey extends AbstractMigration {
    public function up(): void {
        $this->table('users_dupes')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('users_dupes')
            ->dropForeignKey('UserID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();
    }
}
