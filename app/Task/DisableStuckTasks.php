<?php

namespace Gazelle\Task;

use Gazelle\Util\Irc;
use Gazelle\Util\Time;

class DisableStuckTasks extends \Gazelle\Task {
    public function run(): void {
        // If a task fails with a fatal error it will be stuck in a `running` state forever
        $taskList = $this->pg()->allex("
            select th.id_task, th.id_task_history, th.created, t.name
            from task_history th
            inner join task t using (id_task)
            where th.status = 'running'
                and th.created < now() - interval '15 minute'
                and t.is_enabled is true
        ");

        $now = new \DateTimeImmutable();
        foreach ($taskList as $task) {
            $duration = sprintf(
                '%0.2fs',
                $now->format('U.u') - $task['created']->format('U.u')
            );

            Irc::sendMessage(IRC_CHAN_DEV, "Marking stuck task {$task['name']} ($duration) as insane");
            $this->processed++;
            $this->info("Marking stuck task {$task['name']} ($duration) as insane", $task['id_task']);

            $this->pg()->begin();
            $this->pg()->query('
                update task set
                    is_sane = false
                where id_task = $1
                ', $task['id_task']
            );
            $this->pg()->query("
                update task_history set
                    status = 'failed'
                where id_task_history = $1
                ", $task['id_task_history']
            );
            $this->pg()->commit();
        }

        if ($this->processed > 0) {
            self::$cache->delete_value(\Gazelle\TaskScheduler::CACHE_TASKS);
        }
    }
}
