<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUsersInfoNavItems extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('NavItems')
            ->save();
    }

    public function down(): void {
        $this->query("
            ALTER TABLE users_info ADD COLUMN NavItems varchar(255) NOT NULL DEFAULT ''
        ");
    }
}
