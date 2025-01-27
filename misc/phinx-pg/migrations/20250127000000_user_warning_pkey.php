<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserWarningPkey extends AbstractMigration {
    public function up(): void {
        $this->execute('
            alter table user_warning
            drop constraint user_warning_pkey,
            alter column warning DROP NOT NULL,
            add column id_user_warning integer primary key generated always as identity
        ');
    }

    public function down(): void {
        $this->execute('
            alter table user_warning
            drop constraint user_warning_pkey,
            drop column id_user_warning,
            add primary key (id_user, warning)
        ');
    }
}
