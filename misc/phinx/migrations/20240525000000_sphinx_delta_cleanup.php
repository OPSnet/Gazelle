<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SphinxDeltaCleanup extends AbstractMigration {
    public function up(): void {
         $this->table('sphinx_delta')
            ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->save();
         $this->table('sphinx_requests_delta')
            ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('sphinx_delta')
            ->removeColumn('created')
            ->save();
        $this->table('sphinx_requests_delta')
            ->removeColumn('created')
            ->save();
    }
}
