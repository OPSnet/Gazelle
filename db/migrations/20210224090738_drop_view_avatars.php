<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropViewAvatars extends AbstractMigration
{
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('ViewAvatars')
            ->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('ViewAvatars', 'enum', ['default' => '1', 'values' => ['0', '1']])
            ->save();
    }
}
