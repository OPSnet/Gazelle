<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NullableRipper extends AbstractMigration{
    public function up(): void {
        $this->table('torrents_logs')
            ->changeColumn('Ripper', 'string', ['limit' => 255, 'null' => true])
            ->changeColumn('RipperVersion', 'string', ['limit' => 255, 'null' => true])
            ->save();
    }

    public function down(): void {
        $this->table('torrents_logs')
            ->changeColumn('Ripper', 'string', ['limit' => 255, 'null' => false])
            ->changeColumn('RipperVersion', 'string', ['limit' => 255, 'null' => false])
            ->save();
    }
}
