<?php


use Phinx\Migration\AbstractMigration;

class UserSummary extends AbstractMigration
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
        $this->table('users_summary', ['id' => false, 'primary_key' => 'UserID'])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Groups', 'integer', ['limit' => 10, 'default' => 0])
             ->addColumn('PerfectFlacs', 'integer', ['limit' => 10, 'default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID')
             ->create();
    }
}
