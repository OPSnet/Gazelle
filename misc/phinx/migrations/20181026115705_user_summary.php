<?php

use Phinx\Migration\AbstractMigration;

class UserSummary extends AbstractMigration {
    public function change(): void {
        $this->table('users_summary', ['id' => false, 'primary_key' => 'UserID'])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Groups', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('PerfectFlacs', 'integer', ['limit' => 10, 'default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID')
             ->create();
    }
}
