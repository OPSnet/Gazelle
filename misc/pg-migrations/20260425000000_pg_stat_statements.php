<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PgStatStatements extends AbstractMigration {
    public function up(): void {
        $this->query('
            create extension if not exists pg_stat_statements
        ');
    }

    public function down(): void {
        $this->query('
            drop extension if exists pg_stat_statements
        ');
    }
}
