<?php

use Phinx\Migration\AbstractMigration;

class UserSeedbox extends AbstractMigration {
    public function change(): void {
        $this->table('user_seedbox', ['id' => false, 'primary_key' => 'user_seedbox_id'])
            ->addColumn('user_seedbox_id', 'integer',  ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('user_id',         'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('name',            'string',   ['limit' => 20, 'encoding' => 'utf8mb4'])
            ->addColumn('ipaddr',          'integer',  ['limit' => 10, 'signed' => false])
            ->addIndex(['user_id', 'name'],   ['unique' => true])
            ->addIndex(['user_id', 'ipaddr'], ['unique' => true])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
