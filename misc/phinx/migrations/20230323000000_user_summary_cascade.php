<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserSummaryCascade extends AbstractMigration {
    public function up(): void {
        $this->table('user_summary')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('user_summary')
            ->dropForeignKey('user_id')
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
    }
}
