<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForumsPosts extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE forums_posts
            MODIFY AddedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE forums_posts
            MODIFY AddedTime datetime NOT NULL
        ");
    }
}

