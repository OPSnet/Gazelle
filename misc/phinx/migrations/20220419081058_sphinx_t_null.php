<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SphinxTNull extends AbstractMigration {
    public function up(): void {
        $this->table('sphinx_t')
            ->changeColumn('encoding', 'string', ['length' => 15, 'null' => true])
            ->changeColumn('format',   'string', ['length' => 10, 'null' => true])
            ->changeColumn('media',    'string', ['length' => 20])
            ->save();
    }

    public function down(): void {
        $this->table('sphinx_t')
            ->changeColumn('encoding', 'string', ['length' => 30, 'null' => false])
            ->changeColumn('format',   'string', ['length' => 15, 'null' => false])
            ->changeColumn('media',    'string', ['length' => 15])
            ->save();
    }
}
