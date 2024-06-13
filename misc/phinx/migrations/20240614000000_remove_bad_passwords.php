<?php

use Phinx\Migration\AbstractMigration;

class RemoveBadPasswords extends AbstractMigration {
    public function up(): void {
        $this->table('bad_passwords')->drop()->update();
    }

    public function down(): void {
        $this->table('bad_passwords', [
            'id' => false,
            'primary_key' => ['Password'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '',
            'row_format' => 'DYNAMIC',
        ])
        ->addColumn('Password', 'char', [
            'null' => false,
            'limit' => 32,
            'collation' => 'utf8mb4_0900_ai_ci',
            'encoding' => 'utf8',
        ])
        ->create();
    }
}
