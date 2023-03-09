<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UsersInfoDropArtist extends AbstractMigration
{
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('Artist')
            ->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('Artist', 'enum', ['values' => ['0', '1'], 'default' => '0'])
            ->save();
    }
}
