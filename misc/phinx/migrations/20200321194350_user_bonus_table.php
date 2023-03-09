<?php

use Phinx\Migration\AbstractMigration;

class UserBonusTable extends AbstractMigration
{
    public function up(): void {
        $this->table('user_bonus', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('points', 'float', ['default' => '0.0', 'precision' => 20, 'scale' => 5])
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
        $this->execute('
            INSERT INTO user_bonus SELECT ID, BonusPoints FROM users_main
        ');
    }

    public function down(): void {
        $this->table('user_bonus')->drop()->update();
    }
}
