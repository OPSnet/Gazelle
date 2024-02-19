<?php

use Phinx\Migration\AbstractMigration;

class UserStats extends AbstractMigration {
    public function change(): void {
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
