<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UsersHistoryPasswordsNow extends AbstractMigration
{
    public function up(): void {
        $this->table('users_history_passwords')
            ->changeColumn('ChangeTime', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('useragent', 'string', ['limit' => 768, 'encoding' => 'ascii', 'null' => true])
            ->save();

        $this->execute("UPDATE users_history_passwords SET useragent = 'unknown'");

        $this->table('users_history_passwords')
            ->changeColumn('useragent', 'string', ['limit' => 768, 'encoding' => 'ascii', 'null' => false])
            ->save();
    }

    public function down(): void {
        $this->table('users_history_passwords')
            ->changeColumn('ChangeTime', 'datetime', ['null' => false])
            ->removeColumn('useragent')
            ->save();
    }
}
