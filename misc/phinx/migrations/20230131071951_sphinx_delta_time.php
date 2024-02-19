<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SphinxDeltaTime extends AbstractMigration {
    public function up(): void {
        $this->query('ALTER TABLE sphinx_delta MODIFY Time datetime DEFAULT NULL');
    }

    public function down(): void {
        $this->query('ALTER TABLE sphinx_delta MODIFY Time timestamp DEFAULT NULL');
    }
}
