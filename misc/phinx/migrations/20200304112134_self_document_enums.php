<?php

use Phinx\Migration\AbstractMigration;

class SelfDocumentEnums extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')
            ->changeColumn('Enabled', 'enum', [
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2', 'unconfirmed', 'enabled', 'disabled', 'banned'],
            ])
            ->changeColumn('Visible', 'enum', [
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1', 'yes', 'no'],
            ])
            ->update();
    }

    public function down(): void {
        $this->table('users_main')
            ->changeColumn('Enabled', 'enum', [
                'default' => '0',
                'limit' => 1,
                'values' => ['0', '1', '2'],
            ])
            ->changeColumn('Visible', 'enum', [
                'default' => '1',
                'limit' => 1,
                'values' => ['0', '1'],
            ])
            ->update();
    }
}
