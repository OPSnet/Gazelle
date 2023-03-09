<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDefaultVisible extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users_main')
            ->changeColumn('Visible', 'enum', [
                'default' => '1',
                'values'  => ['0', '1', 'yes', 'no'],
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('users_main')
            ->changeColumn('Visible', 'enum', [
                'null'    => true,
                'default' => null,
                'values'  => ['0', '1', 'yes', 'no'],
            ])
            ->save();
    }
}
