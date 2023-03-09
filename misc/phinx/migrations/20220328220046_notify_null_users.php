<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NotifyNullUsers extends AbstractMigration {
    public function up(): void {
        $this->query("
            ALTER TABLE users_notify_filters MODIFY Users mediumtext
        ");
    }

    public function down(): void {
        $this->query("
            ALTER TABLE users_notify_filters MODIFY Users longtext NOT NULL
        ");
    }
}
