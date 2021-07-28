<?php


use Phinx\Migration\AbstractMigration;

class ReferralTracking extends AbstractMigration
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
    public function change() {
        $this->table('referral_users', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
            ->addColumn('Username', 'string', ['limit' => 100])
            ->addColumn('Site', 'string', ['limit' => 100])
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Joined', 'timestamp', ['null' => true])
            ->addColumn('IP', 'string', ['limit' => 15])
            ->addColumn('InviteKey', 'string', ['limit' => 32])
            ->addColumn('Active', 'boolean', ['default' => false])
            ->create();
    }
}
