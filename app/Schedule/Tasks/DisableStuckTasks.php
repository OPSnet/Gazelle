<?php

namespace Gazelle\Schedule\Tasks;

use Gazelle\Util\{Time, Irc};

class DisableStuckTasks extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // If a task fails with a fatal error it will be stuck in a `running` state forever
        $this->db->prepared_query("
            SELECT pth.periodic_task_id, pth.launch_time, pt.name
            FROM periodic_task_history pth
            INNER JOIN periodic_task pt USING (periodic_task_id)
            WHERE pth.status = 'running'
                AND pth.launch_time < now() - INTERVAL 15 MINUTE
                AND pt.is_enabled IS TRUE
        ");

        $tasks = $this->db->to_array(false, MYSQLI_ASSOC);
        foreach ($tasks as $task) {
            list($id, $launchTime, $name) = array_values($task);
            $duration = Time::timeDiff(time() - strtotime($launchTime) + time(), 2, false);

            Irc::sendChannel("Disabling stuck task $name ($duration)", LOG_CHAN);
            $this->processed++;
            $this->info("Disabling stuck task $name ($duration)", $id);

            $this->db->prepared_query('
                UPDATE periodic_task
                SET is_enabled = FALSE,
                    is_sane = FALSE
                WHERE periodic_task_id = ?
            ', $id);
        }

        if ($this->processed > 0) {
            $this->cache->delete_value(\Gazelle\Schedule\Scheduler::CACHE_TASKS);
        }
    }
}
