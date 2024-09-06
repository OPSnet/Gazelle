<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class Applicant extends AbstractMigration {
    public function up(): void {
        $this->table('thread_type', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('Name', 'string', ['limit' => 20])
            ->addIndex(['Name'], ['unique' => true])
            ->create();

        $this->table('thread', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('ThreadTypeID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('ThreadTypeID', 'thread_type', 'ID')
            ->create();

        $this->table('thread_note', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 6, 'signed' => false, 'identity' => true])
            ->addColumn('ThreadID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('Body', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('Visibility', 'enum', ['values' => ['staff', 'public']])
            ->addForeignKey('ThreadID', 'thread', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('applicant_role', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 4, 'signed' => false, 'identity' => true])
            ->addColumn('Title', 'string', ['limit' => 40])
            ->addColumn('Published', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('Description', 'text')
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Modified', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('applicant', ['id' => false, 'primary_key' => 'ID'])
            ->addColumn('ID', 'integer', ['limit' => 4, 'signed' => false, 'identity' => true])
            ->addColumn('RoleID', 'integer', ['limit' => 4, 'signed' => false])
            ->addColumn('UserID', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('ThreadID', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('Body', 'text')
            ->addColumn('Created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Modified', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('Resolved', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'default' => 0])
            ->addForeignKey('RoleID', 'applicant_role', 'ID')
            ->addForeignKey('ThreadID', 'thread', 'ID')
            ->addForeignKey('UserID', 'users_main', 'ID')
            ->create();

        $this->table('thread_type')->insert([
            ['name' => 'staff-pm'],
            ['name' => 'staff-role'],
            ['name' => 'torrent-report']
        ])->save();
    }

    public function down(): void {
        $this->table('applicant')->drop()->update();
        $this->table('applicant_role')->drop()->update();
        $this->table('thread_note')->drop()->update();
        $this->table('thread')->drop()->update();
        $this->table('thread_type')->drop()->update();
    }
}
