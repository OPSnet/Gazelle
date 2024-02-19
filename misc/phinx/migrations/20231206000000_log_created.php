<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LogCreated extends AbstractMigration {
    public function up(): void {
        $this->table('group_log')
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();
        $this->table('log')
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();

        $this->execute("UPDATE group_log SET created = Time");
        $this->execute("UPDATE log SET created = Time");
    }

    public function down(): void {
        $this->table('group_log')->removeColumn('created')->save();
        $this->table('log')->removeColumn('created')->save();
    }
}
