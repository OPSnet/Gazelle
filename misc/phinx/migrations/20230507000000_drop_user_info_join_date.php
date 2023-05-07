<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserInfoJoinDate extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')->removeColumn('JoinDate')->save();
    }

    public function down(): void {
        $this->table('users_info')->addColumn('JoinDate', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])->save();
    }
}
