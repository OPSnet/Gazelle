<?php

namespace Gazelle\Schedule;

use \Gazelle\Util\Irc;

class Scheduler {
    protected $db;
    protected $cache;

    const CACHE_TASKS = 'scheduled_tasks';

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function getTask(int $id) {
        $tasks = $this->getTasks();
        return array_key_exists($id, $tasks) ? $tasks[$id] : null;
    }

    public function getTasks() {
        if (!$tasks = $this->cache->get_value(self::CACHE_TASKS)) {
            $this->db->prepared_query('
                SELECT periodic_task_id, name, classname, description, period, is_enabled, is_sane, is_debug
                FROM periodic_task
            ');

            $tasks = $this->db->has_results() ? $this->db->to_array('periodic_task_id', MYSQLI_ASSOC) : [];
            $this->cache->cache_value(self::CACHE_TASKS, $tasks, 86400 * 30);
        }

        return $tasks;
    }

    public static function isClassValid(string $class) {
        $class = 'Gazelle\\Schedule\\Tasks\\'.$class;
        return class_exists($class);
    }

    public function clearCache() {
        $this->cache->delete_value(self::CACHE_TASKS);
    }

    public function createTask(string $name, string $class, string $description, int $period, bool $isEnabled, bool $isSane, bool $isDebug) {
        if (!self::isClassValid($class)) {
            return;
        }

        $this->db->prepared_query('
            INSERT INTO periodic_task
                   (name, classname, description, period, is_enabled, is_sane, is_debug)
            VALUES
                   (?,    ?,         ?,           ?,      ?,          ?,       ?)
        ', $name, $class, $description, $period, $isEnabled, $isSane, $isDebug);
        $this->clearCache();
    }

    public function updateTask(int $id, string $name, string $class, string $description, int $period, bool $isEnabled, bool $isSane, bool $isDebug) {
        if (!self::isClassValid($class)) {
            return;
        }

        $this->db->prepared_query('
            UPDATE periodic_task
            SET name = ?,
                classname = ?,
                description = ?,
                period = ?,
                is_enabled = ?,
                is_sane = ?,
                is_debug = ?
            WHERE periodic_task_id = ?
        ', $name, $class, $description, $period, $isEnabled, $isSane, $isDebug, $id);
        $this->clearCache();
    }

    public function deleteTask(int $id) {
        $this->db->prepared_query('
            DELETE FROM periodic_task
            WHERE periodic_task_id = ?
        ', $id);
        $this->clearCache();
    }

    public function getTaskDetails() {
        $this->db->query("
            SELECT pt.periodic_task_id, name, description, period, is_enabled, is_sane,
                   coalesce(stats.runs, 0) runs, coalesce(stats.processed, 0) processed,
                   coalesce(stats.errors, 0) errors, coalesce(events.events, 0) events,
                   coalesce(pth.launch_time, '') last_run, coalesce(pth.duration_ms, 0) duration,
                   coalesce(pth.status, '') status
            FROM periodic_task pt
            LEFT JOIN
            (
                SELECT periodic_task_id, max(periodic_task_history_id) AS latest, count(*) AS runs,
                       sum(num_errors) AS errors, sum(num_items) AS processed
                FROM periodic_task_history
                WHERE launch_time > (now() - INTERVAL 14 DAY)
                GROUP BY periodic_task_id
            ) stats USING (periodic_task_id)
            LEFT JOIN
            (
                SELECT pth.periodic_task_id, count(*) AS events
                FROM periodic_task_history_event pthe
                INNER JOIN periodic_task_history pth ON (pthe.periodic_task_history_id = pth.periodic_task_history_id)
                WHERE pth.launch_time > (now() - INTERVAL 14 DAY)
                GROUP BY pth.periodic_task_history_id
            ) events ON (pt.periodic_task_id = events.periodic_task_id)
            LEFT JOIN periodic_task_history pth ON (stats.latest = pth.periodic_task_history_id)
            ORDER BY pt.is_enabled DESC, pt.period, pt.periodic_task_id
        ");

        $tasks = $this->db->has_results() ? $this->db->to_array('periodic_task_id', MYSQLI_ASSOC) : [];
        return $tasks;
    }

    public function getTaskHistory(int $id, string $limit) {
        global $Debug;
        $queryId = $this->db->prepared_query("
            SELECT SQL_CALC_FOUND_ROWS periodic_task_history_id, launch_time, status, num_errors,
                   num_items, duration_ms
            FROM periodic_task_history
            WHERE periodic_task_id = ?
            ORDER BY launch_time DESC
            LIMIT $limit
        ", $id);
        $this->db->prepared_query('SELECT found_rows()');
        list($rowCount) = $this->db->next_record();
        $this->db->set_query_id($queryId);

        $items = $this->db->to_array('periodic_task_history_id', MYSQLI_ASSOC);

        $placeholders = implode(',', array_fill(0, count($items), '?'));
        $this->db->prepared_query("
            SELECT periodic_task_history_id, event_time, severity, event, reference
            FROM periodic_task_history_event
            WHERE periodic_task_history_id IN ($placeholders)
            ORDER BY event_time, periodic_task_history_event_id
        ", ...array_keys($items));
        $events = $this->db->to_array(false, MYSQLI_ASSOC);

        $historyEvents = [];
        foreach ($events as $event) {
            list($historyId, $eventTime, $severity, $message, $reference) = array_values($event);
            $historyEvents[$historyId][] = new Event($severity, $message, $reference, $eventTime);
        }

        $task = new TaskHistory($this->getTask($id)['name'], $rowCount);
        foreach ($items as $item) {
            list($historyId, $launchTime, $status, $numErrors, $numItems, $duration) = array_values($item);
            $taskEvents = array_key_exists($historyId, $historyEvents) ? $historyEvents[$historyId] : [];
            $task->items[] = new HistoryItem($launchTime, $status, $numErrors, $numItems, $duration, $taskEvents);
        }

        return $task;
    }

    public function run() {
        $this->db->prepared_query('
            SELECT pt.periodic_task_id
            FROM periodic_task pt
            WHERE pt.is_enabled IS TRUE
                AND NOT EXISTS (
                    SELECT 1
                    FROM periodic_task_history pth
                    WHERE pth.periodic_task_id = pt.periodic_task_id
                        AND now() < (pth.launch_time + INTERVAL ((pth.duration_ms / 1000) + pt.period) SECOND)
                )
        ');

        $toRun = $this->db->collect('periodic_task_id');

        foreach ($toRun as $id) {
            $this->runTask($id);
        }
    }

    public function runTask(int $id) {
        $task = $this->getTask($id);
        if ($task === null) {
            return;
        }
        echo('Running task '.$task['name']."...");

        $taskRunner = $this->createRunner($id, $task['name'], $task['classname'], $task['is_debug']);
        if ($taskRunner === null) {
            Irc::sendChannel('Failed to construct task '.$task['name'], LAB_CHAN);
            return;
        }

        $taskRunner->begin();
        try {
            $taskRunner->run();
        } catch (\Exception $e) {
            $taskRunner->log('Caught exception: ' . $e->getMessage(), 'error');
        } finally {
            $taskRunner->end($task['is_sane']);
        }
    }

    private function createRunner(int $id, string $name, string $class, bool $isDebug) {
        $class = 'Gazelle\\Schedule\\Tasks\\'.$class;
        if (!class_exists($class)) {
            return null;
        }
        return new $class($this->db, $this->cache, $id, $name, $isDebug);
    }
}
