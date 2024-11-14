<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserEnabledNotNull extends AbstractMigration
{
    public function up(): void {
        $this->table('users_main')
            ->changeColumn('Enabled', 'enum', [
                'default' => '0',
                'values'  => ['0','1','2','unconfirmed','enabled','disabled','banned'],
            ])
            ->save();
    }

    public function down(): void {
        $this->table('users_main')
            ->changeColumn('Enabled', 'enum', [
                'null'    => true,
                'default' => null,
                'values'  => ['0','1','2','unconfirmed','enabled','disabled','banned'],
            ])
            ->save();
    }
}
