<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SchedulerLaunchIndex extends AbstractMigration
{
    public function change(): void
    {
        $this->table('periodic_task_history')
            ->addIndex(['launch_time'], ['name' => 'pth_lt_idx'])
            ->update();
    }
}
