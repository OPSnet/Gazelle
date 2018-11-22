<?php


use Phinx\Migration\AbstractMigration;

class StaffGroups extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
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
}
