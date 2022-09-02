<?php

namespace Gazelle\Schedule\Tasks;

use Gazelle\Util\Irc;
use Gazelle\Util\Time;

class DisableStuckTasks extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // If a task fails with a fatal error it will be stuck in a `running` state forever
        self::$db->prepared_query("
            SELECT pth.periodic_task_id, pth.periodic_task_history_id, pth.launch_time, pt.name
            FROM periodic_task_history pth
            INNER JOIN periodic_task pt USING (periodic_task_id)
            WHERE pth.status = 'running'
                AND pth.launch_time < now() - INTERVAL 15 MINUTE
                AND pt.is_enabled IS TRUE
        ");
        $tasks = self::$db->to_array(false, MYSQLI_ASSOC);

        foreach ($tasks as $task) {
            [$id, $historyId, $launchTime, $name] = array_values($task);
            $duration = Time::diff(time() - strtotime($launchTime) + time(), 2, false);

            Irc::sendMessage(LAB_CHAN, "Marking stuck task $name ($duration) as insane");
            $this->processed++;
            $this->info("Marking stuck task $name ($duration) as insane", $id);

            self::$db->prepared_query('
                UPDATE periodic_task SET
                    is_sane = FALSE
                WHERE periodic_task_id = ?
                ', $id
            );

            self::$db->prepared_query("
                UPDATE periodic_task_history SET
                    status = 'failed'
                WHERE periodic_task_history_id = ?
                ", $historyId
            );
        }

        if ($this->processed > 0) {
            self::$cache->delete_value(\Gazelle\Schedule\Scheduler::CACHE_TASKS);
        }
    }
}
