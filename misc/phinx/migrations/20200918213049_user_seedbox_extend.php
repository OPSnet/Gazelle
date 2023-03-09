<?php

use Phinx\Migration\AbstractMigration;

class UserSeedboxExtend extends AbstractMigration {
    public function up(): void {
        $this->table('user_seedbox')
            ->changeColumn('name',   'string',  ['limit' => 40])
            ->addColumn('peer_id',   'binary',  ['limit' => 20])
            ->addColumn('useragent', 'string',  ['limit' => 51])
            ->addIndex(['peer_id'], ['unique' => true])
            ->update();
    }

    public function down(): void {
        $this->table('user_seedbox')
            ->changeColumn('name',      'string',  ['limit' => 20])
            ->removeColumn('peer')
            ->removeColumn('useragent')
            ->removeIndex(['user_id', 'ipaddr'])
            ->update();
    }
}
