<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserWarning extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE user_warning (
            id_user integer not null,
            id_user_warner integer not null,
            warning tstzrange not null default tstzrange(now(), now() + \'1 week\'::interval),
            primary key (id_user, warning)
        );');
    }

    public function down(): void {
        $this->table('user_warning')->drop()->save();
    }
}
