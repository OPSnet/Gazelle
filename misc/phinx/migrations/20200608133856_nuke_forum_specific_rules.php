<?php

use Phinx\Migration\AbstractMigration;

class NukeForumSpecificRules extends AbstractMigration {
    public function up(): void {
        $this->table('forums_specific_rules')->drop()->update();
    }

    public function down(): void {
        $this->table('forums_specific_rules', ['id' => false])
            ->addColumn('ForumID', 'integer', [
                'null' => true, 'default' => null, 'limit' => '6', 'signed' => false
            ])
            ->addColumn('ThreadID', 'integer', [
                'null' => true, 'default' => null, 'limit' => '10'
            ])
            ->create();
    }
}
