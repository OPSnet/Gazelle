<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserStatsFkeyCascade extends AbstractMigration {
    public function up(): void {
        foreach (['users_stats_daily', 'users_stats_monthly', 'users_stats_yearly'] as $table) {
            $this->table($table)
                 ->dropForeignKey('UserID')
                 ->addForeignKey('UserID', 'users_main', 'ID',
                     ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                 ->save();
        }
    }

    public function down(): void {
        foreach (['users_stats_daily', 'users_stats_monthly', 'users_stats_yearly'] as $table) {
            $this->table($table)
                 ->dropForeignKey('UserID')
                 ->addForeignKey('UserID', 'users_main', 'ID')
                 ->save();
        }
    }
}
