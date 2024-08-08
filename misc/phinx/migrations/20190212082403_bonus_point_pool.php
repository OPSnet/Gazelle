<?php

use Phinx\Migration\AbstractMigration;

class BonusPointPool extends AbstractMigration {
    public function up(): void {
        $this->table('bonus_pool', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('Name', 'string', ['limit' => 80])
            ->addColumn('SinceDate', 'timestamp', ['null' => true])
            ->addColumn('UntilDate', 'timestamp', ['null' => true])
            ->addColumn('Total', 'float', ['default' => 0])
            ->save();

        $this->table('bonus_pool_contrib', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('BonusPoolID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('AmountRecv', 'float')
            ->addColumn('AmountSent', 'float')
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('BonusPoolID', 'bonus_pool', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->save();

        $this->table('contest_has_bonus_pool', ['id' => false, 'primary_key' => ['BonusPoolID', 'ContestID']])
            ->addColumn('BonusPoolID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('ContestID', 'integer', ['limit' => 11])
            ->addForeignKey('BonusPoolID', 'bonus_pool', 'ID')
            ->addForeignKey('ContestID', 'contest', 'ID')
            ->save();

        $this->execute("
            INSERT IGNORE INTO contest_type (Name) VALUES ('upload_flac_no_single')
        ");
    }

    public function down(): void {
        $this->table('bonus_pool_contrib')->drop()->save();
        $this->table('contest_has_bonus_pool')->drop()->save();
        $this->table('bonus_pool')->drop()->save();
    }
}
