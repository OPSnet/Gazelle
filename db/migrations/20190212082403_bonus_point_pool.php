<?php


use Phinx\Migration\AbstractMigration;

class BonusPointPool extends AbstractMigration
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
    public function up()
    {
        $this->table('bonus_pool', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('Name', 'string', ['limit' => 80])
            ->addColumn('SinceDate', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('UntilDate', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Total', 'float', ['default' => 0])
            ->create();

        $this->table('bonus_pool_contrib', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('BonusPoolID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('AmountRecv', 'float')
            ->addColumn('AmountSent', 'float')
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('BonusPoolID', 'bonus_pool', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('contest_has_bonus_pool', ['id' => false, 'primary_key' => ['BonusPoolID', 'ContestID']])
            ->addColumn('BonusPoolID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('ContestID', 'integer', ['limit' => 11])
            ->addForeignKey('BonusPoolID', 'bonus_pool', 'ID')
            ->addForeignKey('ContestID', 'contest', 'ID')
            ->create();

        $rows = $this->getQueryBuilder()
                     ->select('ID')
                     ->from('contest_type')
                     ->where(['Name' => 'upload_flac_no_single'])
                     ->execute()
                     ->fetchAll('assoc');

        if (count($rows) === 0) {
            $this->table('contest_type')->insert([['Name' => 'upload_flac_no_single']])->save();
        }
    }

    public function down()
    {
        $this->table('bonus_pool_contrib')->drop()->update();
        $this->table('contest_has_bonus_pool')->drop()->update();
        $this->table('bonus_pool')->drop()->update();
    }
}
