<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedboxBigint extends AbstractMigration {
    public function up(): void {
        $this->table('user_seedbox')->changeColumn('ipaddr', 'biginteger')->save();
    }

    public function down(): void {
        $this->table('user_seedbox')->changeColumn('ipaddr', 'integer', ['signed' => false])->save();
    }
}
