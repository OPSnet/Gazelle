<?php

use Phinx\Migration\AbstractMigration;

class FinaliseScheduler extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
             ->insert([
                 [
                     'name' => 'Ratio Watch - Torrent History',
                     'classname' => 'TorrentHistory',
                     'description' => 'Calculates seeding torrent counts',
                     'period' => 60 * 60
                 ],
                 [
                     'name' => 'Ratio Watch - Required Ratio',
                     'classname' => 'RatioRequirements',
                     'description' => 'Calculates required ratios',
                     'period' => 60 * 60 * 24
                 ],
                 [
                     'name' => 'Demote Users by Ratio',
                     'classname' => 'DemoteUsersRatio',
                     'description' => 'Demotes users with insufficient ratio',
                     'period' => 60 * 60 * 24
                 ]
             ])
             ->save();

        $this->table('periodic_task_history')
             ->changeColumn('status', 'enum', ['default' => 'running', 'values' => ['running', 'completed', 'failed']])
             ->save();

        $this->execute("
            UPDATE periodic_task_history
            SET status = 'failed'
            WHERE status = 'running'
              AND launch_time < now() - INTERVAL 15 MINUTE
        ");
    }

    public function down(): void {
        $builder = $this->getQueryBuilder();
        $builder->delete('periodic_task')
                ->where(function ($exp) {
                    return $exp->in('classname', ['TorrentHistory', 'RatioRequirements', 'DemoteUsersRatio']);
                })
                ->execute();

        $this->execute("
            UPDATE periodic_task_history
            SET status = 'running'
            WHERE status = 'failed'
        ");

        $this->table('periodic_task_history')
             ->changeColumn('status', 'enum', ['default' => 'running', 'values' => ['running', 'completed']])
             ->save();
    }
}
