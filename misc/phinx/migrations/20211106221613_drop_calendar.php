<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DropCalendar extends AbstractMigration
{
    public function up(): void {
        $this->table('calendar')->drop()->save();
    }

    public function down(): void {
        $this->table('calendar', [
                'id' => false,
                'primary_key' => ['ID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => 10,
                'identity' => true,
            ])
            ->addColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->addColumn('Category', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('StartDate', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('EndDate', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('AddedBy', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
            ])
            ->addColumn('Importance', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('Team', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->create();
    }
}
