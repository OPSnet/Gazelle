<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUnusedTables extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr_drop')->drop()->save();
    }

    public function down(): void {
        $this->query('
            create table user_attr_drop
                id int not null primary key generated always as identity,
                name text not null,
                description text not null
            )
        ');
    }
}
