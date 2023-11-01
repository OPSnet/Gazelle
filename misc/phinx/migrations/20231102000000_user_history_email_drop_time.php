<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserHistoryEmailDropTime extends AbstractMigration {
    public function up(): void {
        $this->table('users_history_emails')
            ->removeColumn('Time')
            ->save();
    }

    public function down(): void {
        $this->table('users_history_emails')
            ->addColumn('Time', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }
}
