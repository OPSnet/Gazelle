<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequestChecksum2 extends AbstractMigration {
    public function up(): void {
        $this->table('requests')
            ->addColumn('Checksum', 'boolean', ['default' => false])
            ->save();
    }

    public function down(): void {
        $this->table('requests')->removeColumn('Checksum')->save();
    }
}
