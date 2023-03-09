<?php

use Phinx\Migration\AbstractMigration;

class BonusPointsMigration extends AbstractMigration {
    public function up(): void {
        $this->table('bonus_item', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('Price',        'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('Amount',       'integer', ['limit' => 2, 'signed' => false, 'null' => true])
            ->addColumn('MinClass',     'integer', ['limit' => 6, 'signed' => false, 'default' => 0])
            ->addColumn('FreeClass',    'integer', ['limit' => 6, 'signed' => false, 'default' => 999999])
            ->addColumn('Label',        'string',  ['limit' => 32])
            ->addColumn('Title',        'string',  ['limit' => 64])
            ->addIndex(['Label'], ['unique' => true])
            ->create();

        $this->table('bonus_history', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID',           'integer',  ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('ItemID',       'integer',  ['limit' => 6, 'signed' => false])
            ->addColumn('UserID',       'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('Price',        'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('OtherUserID',  'integer',  ['limit' => 10, 'signed' => false, 'null' => true])
            ->addColumn('PurchaseDate', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('UserID', 'users_main', 'ID', ['constraint' => 'bonus_history_fk_user'])
            ->addForeignKey('ItemID', 'bonus_item', 'ID', ['constraint' => 'bonus_history_fk_item'])
            ->create();

        $this->table('bonus_item')->insert([
            ['Price' =>   1000, 'Amount' =>  1, 'Label' => 'token-1', 'Title' => '1 Freeleech Token'],
            ['Price' =>   9500, 'Amount' => 10, 'Label' => 'token-2', 'Title' => '10 Freeleech Tokens'],
            ['Price' =>  45000, 'Amount' => 50, 'Label' => 'token-3', 'Title' => '50 Freeleech Tokens'],
            ['Price' =>   2500, 'Amount' =>  1, 'Label' => 'other-1', 'Title' => '1 Freeleech Token to Other'],
            ['Price' =>  24000, 'Amount' => 10, 'Label' => 'other-2', 'Title' => '10 Freeleech Tokens to Other'],
            ['Price' => 115000, 'Amount' => 50, 'Label' => 'other-3', 'Title' => '50 Freeleech Tokens to Other'],

            ['Price' =>  20000, 'Amount' =>  1, 'Label' => 'invite', 'MinClass' => 150, 'title' => 'Buy an Invite'],

            ['Price' =>  50000, 'Label' => 'title-bb-n', 'FreeClass' => 400, 'Title' => 'Custom Title (No BBCode)'],
            ['Price' => 150000, 'Label' => 'title-bb-y', 'FreeClass' => 400, 'Title' => 'Custom Title (BBCode Allowed)'],
            ['Price' =>      0, 'Label' => 'title-off',  'Title' => 'Remove Custom Title'],
        ])->save();
    }

    public function down(): void {
        $this->table('bonus_history')->drop()->update();
        $this->table('bonus_item')->drop()->update();
    }
}
