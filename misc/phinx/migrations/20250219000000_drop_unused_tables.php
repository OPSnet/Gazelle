<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DropUnusedTables extends AbstractMigration {
    public function up(): void {
        $this->table('do_not_upload')->drop()->save();
        $this->table('invite_tree')->drop()->save();
    }

    public function down(): void {
        $this->table('do_not_upload', ['id' => false, 'primary_key' => ['ID']])
            ->addColumn('ID',      'integer', ['identity' => true])
            ->addColumn('Name',    'string', ['limit' => 255])
            ->addColumn('Comment', 'string', ['limit' => 255])
            ->addColumn('UserID',  'integer')
            ->addColumn('Time',    'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Sequence', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM])
            ->addIndex(['Time'], ['name' => 'Time'])
            ->create();

        $this->table('invite_tree', ['id' => false, 'primary_key' => ['UserID']])
            ->addColumn('UserID', 'integer', ['default' => '0'])
            ->addColumn('InviterID', 'integer', ['null' => true])
            ->addColumn('TreePosition', 'integer', ['default' => '1'])
            ->addColumn('TreeID', 'integer', ['default' => '1'])
            ->addColumn('TreeLevel', 'integer', ['default' => '0'])
            ->addIndex(['InviterID'], ['name' => 'InviterID'])
            ->addIndex(['TreePosition'], ['name' => 'TreePosition'])
            ->addIndex(['TreeID'], ['name' => 'TreeID'])
            ->addIndex(['TreeLevel'], ['name' => 'TreeLevel'])
            ->save();
    }
}
