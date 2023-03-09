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
                 ]
             ])
             ->save();
    }

    public function down(): void {
        $builder = $this->getQueryBuilder();
        $builder->delete('periodic_task')
                ->where(function ($exp) {
                    return $exp->in('classname', ['PromoteUsers', 'DemoteUsers']);
                })
                ->execute();
    }
}
