<?php

use Phinx\Migration\AbstractMigration;

class PeriodicRunNow extends AbstractMigration {
    public function change(): void {
        $this->table('periodic_task')
             ->addColumn('run_now', 'boolean', ['default' => false])
             ->update();
    }
}
