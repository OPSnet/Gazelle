<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Counter extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE counter (
            name varchar(20) not null primary key,
            description text not null check(length(description) <= 2000),
            value integer not null default 0
        );');
    }

    public function down(): void {
        $this->table('counter')->drop()->save();
    }
}
