<?php


use Phinx\Migration\AbstractMigration;

class PaymentReminder extends AbstractMigration
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
        $this->table('payment_reminders', ['id' => false, 'primary_key' => 'ID'])
             ->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
             ->addColumn('Text', 'string', ['limit' => 100])
             ->addColumn('Expiry', 'timestamp', ['null' => true])
             ->addColumn('Active', 'boolean', ['default' => true])
             ->create();
    }
}
