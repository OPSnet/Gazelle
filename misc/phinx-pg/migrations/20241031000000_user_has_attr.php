<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserHasAttr extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE user_has_attr (
                id INTEGER NOT NULL PRIMARY KEY generated always as identity,
                id_user INTEGER NOT NULL,
                id_user_attr INTEGER NOT NULL,
                CONSTRAINT no_duplicate_attr UNIQUE (id_user, id_user_attr)
            )');

        $this->execute('CREATE TABLE user_attr (
            id INTEGER NOT NULL PRIMARY KEY generated always as identity,
            name TEXT NOT NULL,
            description TEXT NOT NULL
        )');

        foreach (\Gazelle\Enum\NotificationType::cases() as $attr) {
            $attr = strtolower($attr->toString());
            $this->execute("INSERT INTO user_attr(name, description) VALUES
                ('{$attr}_pop', 'Get a pop-up notification on {$attr}'),
                ('{$attr}_trad', 'Get a traditional notification on {$attr}'),
                ('{$attr}_push', 'Get a push notification on {$attr}');
            ");
        }
    }

    public function down(): void {
        $this->table('user_has_attr')->drop()->save();
        $this->table('user_attr')->drop()->save();
    }
}
