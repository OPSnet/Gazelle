<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class DropReportsEmailBlacklist extends AbstractMigration {
    public function up(): void {
        $this->table('reports_email_blacklist')->drop()->update();
    }

    public function down(): void {
        $this->table('reports_email_blacklist', ['id' => false, 'primary_key' => ['ID']])
            ->addColumn('ID',         'integer', ['null' => false, 'limit' => 10, 'identity' => true])
            ->addColumn('Type',       'integer', ['null' => false, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('UserID',     'integer', ['null' => false, 'limit' => 10])
            ->addColumn('Time',       'datetime', ['null' => true])
            ->addColumn('Checked',    'integer', ['null' => false, 'default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('ResolverID', 'integer', ['null' => true, 'default' => '0', 'limit' => 10])
            ->addColumn('Email',      'string', ['null' => false, 'default' => '', 'limit' => 255])
            ->addIndex(['Time'],   ['name' => 'Time', 'unique' => false])
            ->addIndex(['UserID'], ['name' => 'UserID', 'unique' => false])
            ->create();
    }
}
