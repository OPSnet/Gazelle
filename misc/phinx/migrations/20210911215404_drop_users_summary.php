<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUsersSummary extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users_summary')->drop()->update();
    }

    public function down(): void
    {
        $this->table('users_summary', ['id' => false, 'primary_key' => 'UserID'])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Groups', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('PerfectFlacs', 'integer', ['limit' => 10, 'default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID')
             ->create();
    }
}
