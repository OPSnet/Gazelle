<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserMainSecret extends AbstractMigration {
    public function up(): void {
        $this->table('users_main')->removeColumn('Secret')->save();
        $this->query('ALTER TABLE users_info
            MODIFY Info mediumtext,
            MODIFY SiteOptions mediumtext
        ');
    }
    public function down(): void {
        $this->table('users_main')->removeColumn('Secret')->save();
        $this->query('ALTER TABLE users_info
            MODIFY Info longtext NOT NULL,
            MODIFY SiteOptions longtext NOT NULL
        ');
    }
}
