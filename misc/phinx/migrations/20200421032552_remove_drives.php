<?php

use Phinx\Migration\AbstractMigration;

class RemoveDrives extends AbstractMigration {
    public function up(): void {
        $this->table('drives')->drop()->update();
    }

    public function down(): void {
        $this->table('drives', [
            'id' => false,
            'primary_key' => ['DriveID'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8',
            'collation' => 'utf8_general_ci',
            'comment' => '',
            'row_format' => 'DYNAMIC',
        ])
        ->addColumn('DriveID', 'integer', [
            'null' => false,
            'limit' => '10',
            'identity' => 'enable',
        ])
        ->addColumn('Name', 'string', [
            'null' => false,
            'limit' => 50,
            'collation' => 'utf8_general_ci',
            'encoding' => 'utf8',
        ])
        ->addColumn('Offset', 'string', [
            'null' => false,
            'limit' => 10,
            'collation' => 'utf8_general_ci',
            'encoding' => 'utf8',
        ])
        ->addIndex(['Name'], [
            'name' => 'Name',
            'unique' => false,
        ])
        ->create();
    }
}
