<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TaskFkeyCascade extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task_history')
             ->dropForeignKey('periodic_task_id')
             ->addForeignKey('periodic_task_id', 'periodic_task', 'periodic_task_id',
                 ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->save();

        $this->table('periodic_task_history_event')
             ->dropForeignKey('periodic_task_history_id')
             ->addForeignKey('periodic_task_history_id', 'periodic_task_history', 'periodic_task_history_id',
                 ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->save();
    }

    public function down(): void {
        $this->table('periodic_task_history')
             ->dropForeignKey('periodic_task_id')
             ->addForeignKey('periodic_task_id', 'periodic_task', 'periodic_task_id')
             ->save();

        $this->table('periodic_task_history_event')
             ->dropForeignKey('periodic_task_history_id')
             ->addForeignKey('periodic_task_history_id', 'periodic_task_history', 'periodic_task_history_id')
             ->save();
    }
}
