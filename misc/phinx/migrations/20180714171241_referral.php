<?php


use Phinx\Migration\AbstractMigration;

class Referral extends AbstractMigration
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
    public function change(): void {
        $this->table('referral_accounts', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('Site', 'string', ['limit' => 100])
            ->addColumn('URL', 'string', ['limit' => 100])
            ->addColumn('User', 'string', ['limit' => 100])
            ->addColumn('Password', 'string', ['limit' => 196])
            ->addColumn('Active', 'boolean')
            ->addColumn('Cookie', 'string', ['limit' => 1024])
            ->addColumn('Type', 'integer', ['limit' => 3, 'signed' => false])
            ->create();
    }
}
