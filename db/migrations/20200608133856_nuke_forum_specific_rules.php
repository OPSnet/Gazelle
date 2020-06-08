<?php

use Phinx\Migration\AbstractMigration;

class NukeForumSpecificRules extends AbstractMigration {

    public function up() {
        $this->table('forums_specific_rules')->drop()->update();
    }

    public function down() {
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
