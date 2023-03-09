<?php

use Phinx\Migration\AbstractMigration;

class ForumThreadTitle extends AbstractMigration {
    public function up(): void {
        $this->table('forums_topics')
            ->changeColumn('Title', 'string', ['collation' => 'utf8mb4_unicode_ci'])
            ->update();
    }
}
