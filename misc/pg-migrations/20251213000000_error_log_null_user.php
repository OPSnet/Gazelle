<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ErrorLogNullUser extends AbstractMigration {
    public function up(): void {
        $this->query("
            alter table error_log
                alter column id_user drop default,
                alter column id_user drop not null
        ");
    }

    public function down(): void {
        $this->query("
            alter table error_log
                alter column id_user set default 0,
                alter column id_user set not null
        ");
    }
}
