<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SphinxDeltaGroupName extends AbstractMigration {
    public function up(): void {
        $this->table('sphinx_delta')
            ->changeColumn('GroupName', 'string', ['length' => 300, 'null' => true])
            ->save();
    }

    public function down(): void {
        $this->table('sphinx_delta')
            ->changeColumn('GroupName', 'string', ['length' => 300])
            ->save();
    }
}
