<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PushOptions extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE user_push_options (
                id_user INTEGER NOT NULL,
                push_token TEXT NOT NULL UNIQUE,
                created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_user)
            )');
    }

    public function down(): void {
        $this->table('push_options')->drop()->save();
    }
}
