<?php

use Phinx\Migration\AbstractMigration;

class StaffGroups extends AbstractMigration {
    public function up(): void {
        $this->table('staff_groups', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 3, 'signed' => false, 'identity' => true])
             ->addColumn('Sort', 'integer', ['limit' => 4, 'signed' => false,])
             ->addColumn('Name', 'text', ['limit' => 50])
             ->addIndex('Name', ['unique' => true, 'limit' => 50])
             ->create();

        $this->table('permissions')
             ->addColumn('StaffGroup', 'integer', ['limit' => 3, 'null' => true, 'default' => null, 'signed' => false])
             ->addForeignKey('StaffGroup', 'staff_groups', 'ID', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
             ->update();
    }

    public function down(): void {
        $this->table('permissions')
             ->dropForeignKey('StaffGroup')
             ->update();
        $this->table('permissions')
             ->removeColumn('StaffGroup')
             ->update();

        $this->table('staff_groups')->drop()->update();
    }
}
