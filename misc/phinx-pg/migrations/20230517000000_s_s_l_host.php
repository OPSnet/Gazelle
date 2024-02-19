<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SSLHost extends AbstractMigration {
    public function up(): void {
        $table = $this->table('ssl_host', ['id' => 'id_ssl_host']);
        $table->addColumn('hostname', 'text', ['null' => false])
            ->addColumn('port', 'integer', ['null' => false])
            ->addColumn('not_before', 'timestamp', ['null' => false])
            ->addColumn('not_after', 'timestamp', ['null' => false])
            ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }

    public function down(): void {
        $this->table('ssl_host')->drop()->save();
    }
}
