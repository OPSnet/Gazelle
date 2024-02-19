<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserDownloadDefaultNow extends AbstractMigration
{
    public function up(): void {
        $this->table('users_downloads')
            ->changeColumn('Time', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->save();
    }

    public function down(): void {
        $this->table('users_downloads')
            ->changeColumn('Time', 'datetime', ['null' => false])
            ->save();
    }
}
