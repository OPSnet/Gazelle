<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SphinxDeltaTaglist extends AbstractMigration {
    public function up(): void {
        $this->query('
            ALTER TABLE sphinx_delta
                MODIFY Media varchar(20),
                MODIFY TagList varchar(500)
        ');
    }

    public function down(): void {
        $this->query('
            ALTER TABLE sphinx_delta
                MODIFY Media varchar(20) NOT NULL,
                MODIFY TagList varchar(500) NOT NULL
        ');
    }
}
