<?php

use Phinx\Migration\AbstractMigration;

class PromotionTasks extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
            ->insert([
                [
                    'name' => 'Promote Users',
                    'classname' => 'PromoteUsers',
                    'description' => 'Promotes users',
                    'period' => 60 * 60
                ],
                [
                    'name' => 'Demote Users',
                    'classname' => 'DemoteUsers',
                    'description' => 'Demotes users',
                    'period' => 60 * 60
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM periodic_task WHERE classname IN ('PromoteUsers', 'DemoteUsers')
        ");
    }
}
