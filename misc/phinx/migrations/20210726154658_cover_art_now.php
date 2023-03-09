<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CoverArtNow extends AbstractMigration
{
    public function up(): void {
        $this->table('cover_art')
            ->changeColumn('Image', 'string', ['length' => 255, 'null' => false])
            ->changeColumn('Summary', 'string', ['length' => 100, 'null' => false])
            ->changeColumn('UserID', 'integer', ['length' => 10, 'null' => false])
            ->changeColumn('Time', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('cover_art')
            ->changeColumn('Image', 'string', ['length' => 255, 'null' => false, 'default' => ''])
            ->changeColumn('Summary', 'string', ['length' => 100, 'null' => true])
            ->changeColumn('UserID', 'integer', ['length' => 10, 'null' => false, 'default' => 0])
            ->changeColumn('Time', 'datetime', ['null' => true])
            ->save();
    }
}
