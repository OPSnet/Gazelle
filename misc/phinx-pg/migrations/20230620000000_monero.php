<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Monero extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE donate_monero (
            id_user integer not null primary key,
            token bytea not null unique
        );');
        $this->execute('CREATE EXTENSION IF NOT EXISTS pgcrypto;');
    }

    public function down(): void {
        $this->table('donate_monero')->drop()->save();
        $this->execute('DROP EXTENSION pgcrypto');
    }
}
