<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Pgvector extends AbstractMigration {
    public function up(): void {
        $this->query("create extension if not exists vector");
    }

    public function down(): void {
        $this->query("drop extension if exists vector");
    }
}
