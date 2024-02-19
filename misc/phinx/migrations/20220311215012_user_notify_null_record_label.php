<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserNotifyNullRecordLabel extends AbstractMigration {
    public function up(): void {
        $this->query("
            ALTER TABLE users_notify_filters MODIFY RecordLabels text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ");
    }

    public function down(): void {
        $this->query("
            ALTER TABLE users_notify_filters MODIFY RecordLabels longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ");
    }
}
