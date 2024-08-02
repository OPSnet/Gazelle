<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UploadNotifier extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
            ->insert([[
                'name'        => 'Generate upload notifications',
                'classname'   => 'UploadNotifier',
                'description' => 'Generate notifications for people with filters on uploads',
                'period'      => 1,
                'is_enabled'  => 0,
            ]])
            ->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'UploadNotifier'
        ");
    }
}
