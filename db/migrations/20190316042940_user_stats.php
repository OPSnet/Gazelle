<?php


use Phinx\Migration\AbstractMigration;

class UserStats extends AbstractMigration
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
        $this->table('users_stats_daily', ['id' => false, 'primary_key' => ['UserID', 'Time']])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('Uploaded', 'biginteger', ['default' => 0])
             ->addColumn('Downloaded', 'biginteger', ['default' => 0])
             ->addColumn('BonusPoints', 'float', ['default' => 0, 'precision' => 20, 'scale' => 5])
             ->addColumn('Torrents', 'integer', ['default' => 0])
             ->addColumn('PerfectFLACs', 'integer', ['default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        $this->table('users_stats_monthly', ['id' => false, 'primary_key' => ['UserID', 'Time']])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('Uploaded', 'biginteger', ['default' => 0])
             ->addColumn('Downloaded', 'biginteger', ['default' => 0])
             ->addColumn('BonusPoints', 'float', ['default' => 0, 'precision' => 20, 'scale' => 5])
             ->addColumn('Torrents', 'integer', ['default' => 0])
             ->addColumn('PerfectFLACs', 'integer', ['default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        $this->table('users_stats_yearly', ['id' => false, 'primary_key' => ['UserID', 'Time']])
             ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('Time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('Uploaded', 'biginteger', ['default' => 0])
             ->addColumn('Downloaded', 'biginteger', ['default' => 0])
             ->addColumn('BonusPoints', 'float', ['default' => 0, 'precision' => 20, 'scale' => 5])
             ->addColumn('Torrents', 'integer', ['default' => 0])
             ->addColumn('PerfectFLACs', 'integer', ['default' => 0])
             ->addForeignKey('UserID', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();
    }
}
