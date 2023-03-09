<?php

use Phinx\Migration\AbstractMigration;

class ForumTransitionPermissions extends AbstractMigration {
    public function up(): void {
        $this->table('forums_transitions')
             ->addColumn('permission_class', 'integer', ['limit' => 10, 'signed' => false, 'default' => 800])
             ->addColumn('permissions', 'string', ['limit' => 100, 'default' => ''])
             ->addColumn('user_ids', 'string', ['limit' => 100, 'default' => ''])
             ->update();

        $this->table('forums_topic_notes')
             ->changeColumn('AddedTime', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->update();
    }

    public function down(): void {
        $this->table('forums_transitions')
             ->removeColumn('permission_class')
             ->removeColumn('permissions')
             ->removeColumn('user_ids')
             ->update();

        $this->table('forums_topic_notes')
             ->changeColumn('AddedTime', 'datetime')
             ->update();
    }
}
