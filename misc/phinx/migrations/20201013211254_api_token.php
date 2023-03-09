<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class ApiToken extends AbstractMigration {
    public function up(): void {
        $this->table('api_users')->drop()->update();
        $this->table('api_tokens')
            ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 40])
            ->addColumn('token', 'string', ['limit' => 255])
            ->addColumn('scope', 'text')
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('revoked', 'integer', ['default' => 0, 'limit' => MysqlAdapter::INT_TINY])
            ->addIndex(['user_id'], ['unique' => false])
            ->addIndex(['user_id', 'name'], ['unique' => true])
            ->addIndex(['token'], ['unique' => true])
            ->create();
    }

    public function down(): void {
        $this->table('api_users', ['id' => false, 'primary_key' => ['UserID', 'AppID']])
            ->addColumn('UserID', 'integer', ['null' => false, 'limit' => '10'])
            ->addColumn('AppID', 'integer', ['null' => false, 'limit' => '10'])
            ->addColumn('Token', 'char', ['null' => false, 'limit' => 32, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->addColumn('State', 'enum', ['null' => false, 'default' => '0', 'limit' => 1, 'values' => ['0', '1', '2']])
            ->addColumn('Time', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Access', 'text', ['null' => false, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->create();
        $this->table('api_tokens')->drop()->update();
    }
}
