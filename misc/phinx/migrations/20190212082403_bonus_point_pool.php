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

        $rows = (array)$this->getQueryBuilder()
            ->select('ID')
            ->from('contest_type')
            ->where(['Name' => 'upload_flac_no_single'])
            ->execute()
            ->fetchAll('assoc');

        if (count($rows) === 0) {
            $this->table('contest_type')->insert([['Name' => 'upload_flac_no_single']])->save();
        }
    }

    public function down(): void {
        $this->table('bonus_pool_contrib')->drop()->update();
        $this->table('contest_has_bonus_pool')->drop()->update();
        $this->table('bonus_pool')->drop()->update();
    }
}
