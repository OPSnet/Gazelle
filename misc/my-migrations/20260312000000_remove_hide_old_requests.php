<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveHideOldRequests extends AbstractMigration {
    public function up(): void {
        $this->query("
            DELETE FROM periodic_task WHERE classname = 'HideOldRequests'
        ");
    }

    public function down(): void {
        $this->table('periodic_task')
             ->insert([
                'name'        => 'Hide Old Requests',
                'classname'   => 'HideOldRequests',
                'description' => 'Hides old filled requests',
                'period'      => 3600
            ])
             ->save();
    }
}
