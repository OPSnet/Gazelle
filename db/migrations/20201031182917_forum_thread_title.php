<?php

use Phinx\Migration\AbstractMigration;

class ForumThreadTitle extends AbstractMigration {
    public function up() {
        $this->table('forums_topics')
            ->changeColumn('Title', 'string', ['collation' => 'utf8mb4_bin'])
            ->update();
    }

    public function down() {
        $this->table('forums_topics')
            ->changeColumn('Title', 'string', ['collation' => 'utf8'])
            ->update();
    }
}
