<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ForumAutoSub extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE forum_autosub (
                id_forum INTEGER NOT NULL,
                id_user INTEGER NOT NULL,
                created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_forum, id_user)
            )');
        $this->execute('CREATE INDEX fas_user_idx ON forum_autosub (id_user)');
    }

    public function down(): void {
        $this->table('forum_autosub')->drop()->save();
    }
}
