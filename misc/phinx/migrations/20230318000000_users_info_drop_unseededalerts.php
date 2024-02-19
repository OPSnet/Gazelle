<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UsersInfoDropUnseededAlerts extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')->removeColumn('UnseededAlerts')->save();
    }

    public function down(): void {
        $this->table('users_info')->addColumn('UnseededAlerts', 'enum', [
            'null' => false,
            'default' => '0',
            'limit' => 1,
            'values' => ['0', '1'],
        ])->save();
    }
}
