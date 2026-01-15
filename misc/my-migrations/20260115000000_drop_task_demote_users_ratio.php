<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropTaskDemoteUsersRatio extends AbstractMigration {
    public function up(): void {
        $this->execute("
            DELETE pt, pth, pthe
            FROM periodic_task pt
            LEFT JOIN periodic_task_history pth USING (periodic_task_id)
            LEFT JOIN periodic_task_history_event pthe USING (periodic_task_history_id)
            WHERE pt.classname IN ('DemoteUsersRatio')
        ");
    }

    public function down(): void {
        $this->table('periodic_task')
            ->insert([[
                 'name'        => 'Demote Users by Ratio',
                 'classname'   => 'DemoteUsersRatio',
                 'description' => 'Demotes users with insufficient ratio',
                 'period'      => 60 * 60 * 24
             ]])
             ->save();
    }
}
