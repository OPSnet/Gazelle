<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserNotifyFilterCascade extends AbstractMigration {
    public function up(): void {
        $this->table('users_notify_filters')
            ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('users_notify_filters')
            ->dropForeignKey('UserID')
            ->save();
    }
}
