<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LongerSeedboxName extends AbstractMigration {
    public function up(): void {
        $this->table('user_seedbox')
            ->changeColumn('name', 'string', ['limit' => 100])
            ->save();
    }

    public function down(): void {
        $this->table('user_seedbox')
            ->changeColumn('name', 'string', ['limit' => 40])
            ->save();
    }
}
