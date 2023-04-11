<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Bitcoin extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE TABLE donate_bitcoin (
            id_user integer not null primary key,
            address text not null unique
        );');
        $this->execute("insert into counter (name, description, value)
            values ('donation-bitcoin', 'bitcoin donation address HD path counter', -1);");
    }

    public function down(): void {
        $this->table('donate_bitcoin')->drop()->save();
        $this->execute("DELETE FROM counter WHERE name = 'donation-bitcoin';");
    }
}
