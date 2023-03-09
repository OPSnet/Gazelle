<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UsersHistoryEmailUseragent extends AbstractMigration
{
    public function up(): void {
        $this->table('users_history_emails')
            ->addColumn('useragent', 'string', ['limit' => 768, 'encoding' => 'ascii', 'null' => true])
            ->save();

        $this->execute("UPDATE users_history_emails SET useragent = 'unknown'");

        $this->table('users_history_emails')
            ->changeColumn('useragent', 'string', ['limit' => 768, 'encoding' => 'ascii', 'null' => false])
            ->save();
    }

    public function down(): void {
        $this->table('users_history_emails')
            ->removeColumn('useragent')
            ->save();
    }
}
