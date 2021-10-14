<?php

namespace Gazelle\Schedule;

use \Gazelle\Util\Irc;

class Scheduler extends \Gazelle\Base {

    const CACHE_TASKS = 'scheduled_tasks';

    public function getTask(int $id) {
        $tasks = $this->getTasks();
        return array_key_exists($id, $tasks) ? $tasks[$id] : null;
    }

    public function getTasks() {
        if (!$tasks = $this->cache->get_value(self::CACHE_TASKS)) {
            $this->db->prepared_query('
                SELECT periodic_task_id, name, classname, description, period, is_enabled, is_sane, is_debug, run_now
                FROM periodic_task
            ');

            $tasks = $this->db->has_results() ? $this->db->to_array('periodic_task_id', MYSQLI_ASSOC) : [];
            $this->cache->cache_value(self::CACHE_TASKS, $tasks, 3600);
        }

        return $tasks;
    }

    public function getInsaneTasks() {
        return count(array_filter($this->getTasks(),
            function($v) {
                return !$v['is_sane'];
            }
        ));
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

    public function runNow(int $id) {
        $this->db->prepared_query('
            UPDATE periodic_task
            SET run_now = 1 - run_now
            WHERE periodic_task_id = ?
            ', $id
        );
        $this->clearCache();
    }

    public function deleteTask(int $id) {
        $this->db->prepared_query('
            DELETE FROM periodic_task
            WHERE periodic_task_id = ?
        ', $id);
        $this->clearCache();
    }

    public function getTaskDetails(int $days = 7): array {
        $this->db->prepared_query("
            SELECT pt.periodic_task_id, name, description, period, is_enabled, is_sane, run_now,
                   coalesce(stats.runs, 0) runs, coalesce(stats.processed, 0) processed,
                   coalesce(stats.errors, 0) errors, coalesce(events.events, 0) events,
                   coalesce(pth.launch_time, '') last_run,
                   coalesce(pth.duration_ms, 0) duration,
                   coalesce(pth.status, '') status,
                   if(pth.launch_time is null, now(), pth.launch_time + INTERVAL period SECOND) AS next_run
            FROM periodic_task pt
            LEFT JOIN
            (
                SELECT periodic_task_id, max(periodic_task_history_id) AS latest, count(*) AS runs,
                       sum(num_errors) AS errors, sum(num_items) AS processed
                FROM periodic_task_history
                WHERE launch_time > (now() - INTERVAL ? DAY)
                GROUP BY periodic_task_id
            ) stats USING (periodic_task_id)
            LEFT JOIN
            (
                SELECT pth.periodic_task_id, count(*) AS events
                FROM periodic_task_history_event pthe
                INNER JOIN periodic_task_history pth ON (pthe.periodic_task_history_id = pth.periodic_task_history_id)
                WHERE pth.launch_time > (now() - INTERVAL ? DAY)
                GROUP BY pth.periodic_task_id
            ) events ON (pt.periodic_task_id = events.periodic_task_id)
            LEFT JOIN periodic_task_history pth ON (stats.latest = pth.periodic_task_history_id)
            ORDER BY pt.run_now DESC, pt.is_enabled DESC, pt.period, pt.periodic_task_id
        ", $days, $days);

        return $this->db->to_array('periodic_task_id', MYSQLI_ASSOC, false);
    }

    public function getTotal(int $id): int {
        return $this->db->scalar("
            SELECT count(*) FROM periodic_task_history WHERE periodic_task_id = ?
            ", $id
        );
    }

    public function getTaskHistory(int $id, int $limit, int $offset, string $sort, string $direction) {
        $sortMap = [
            'id'         => 'periodic_task_history_id',
            'launchtime' => 'launch_time',
            'status'     => 'status',
            'errors'     => 'num_errors',
            'items'      => 'num_items',
            'duration'   => 'duration_ms'
        ];

        if (!isset($sortMap[$sort])) {
            return null;
        }
        $sort = $sortMap[$sort];

        $this->db->prepared_query("
            SELECT periodic_task_history_id, launch_time, status, num_errors, num_items, duration_ms
            FROM periodic_task_history
            WHERE periodic_task_id = ?
            ORDER BY $sort $direction
            LIMIT ? OFFSET ?
        ", $id, $limit, $offset);
        $items = $this->db->to_array('periodic_task_history_id', MYSQLI_ASSOC);

        $historyEvents = [];
        if (count($items)) {
            $this->db->prepared_query("
                SELECT periodic_task_history_id, event_time, severity, event, reference
                FROM periodic_task_history_event
                WHERE periodic_task_history_id IN (" . placeholders($items) . ")
                ORDER BY event_time, periodic_task_history_event_id
            ", ...array_keys($items));
            $events = $this->db->to_array(false, MYSQLI_ASSOC);

            foreach ($events as $event) {
                list($historyId, $eventTime, $severity, $message, $reference) = array_values($event);
                $historyEvents[$historyId][] = new Event($severity, $message, $reference, $eventTime);
            }
        }

        $task = new TaskHistory($this->getTask($id)['name'], $this->getTotal($id));
        foreach ($items as $item) {
            list($historyId, $launchTime, $status, $numErrors, $numItems, $duration) = array_values($item);
            $taskEvents = $historyEvents[$historyId] ?? [];
            $task->items[] = new HistoryItem($launchTime, $status, $numErrors, $numItems, $duration, $taskEvents);
        }

        return $task;
    }

    private function constructAxes(array $data, string $key, array $axes, bool $time): array {
        $result = [];

        foreach ($axes as $axis) {
            if (is_array($axis)) {
                $id = $axis[0];
                $name = $axis[1];
            } else {
                $id = $axis;
                $name = $axis;
            }

            $result[] = [
                'name' => $name,
                'data' => array_map(
                    function ($v) use ($id, $key, $time) {
                        return [$time ? strtotime($v[$key]) * 1000 : $v[$key], (int)$v[$id]];
                    },
                    $data
                )
            ];
        }
        return $result;
    }

    public function getRuntimeStats(int $days = 28) {
        $this->db->prepared_query("
            SELECT date_format(pth.launch_time, '%Y-%m-%d %H:00:00') AS date,
                   sum(pth.duration_ms) AS duration,
                   sum(pth.num_items) AS processed
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pt.is_enabled IS TRUE
              AND pth.launch_time >= now() - INTERVAL 1 DAY
            GROUP BY 1
            ORDER BY 1
        ");
        $hourly = $this->constructAxes($this->db->to_array(false, MYSQLI_ASSOC, false), 'date', ['duration', 'processed'], true);

        $this->db->prepared_query("
            SELECT date(pth.launch_time) AS date,
                   sum(pth.duration_ms) AS duration,
                   sum(pth.num_items) AS processed
                   FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pt.is_enabled IS TRUE
              AND pth.launch_time BETWEEN curdate() - INTERVAL ? DAY AND curdate()
            GROUP BY 1
            ORDER BY 1
            ", $days
        );
        $daily = $this->constructAxes($this->db->to_array(false, MYSQLI_ASSOC, false), 'date', ['duration', 'processed'], true);

        $this->db->prepared_query("
            SELECT pt.name,
                   cast(avg(pth.duration_ms) AS INTEGER) AS duration_avg,
                   cast(avg(pth.num_items) AS INTEGER) AS processed_avg
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pt.is_enabled IS TRUE
              AND pth.launch_time BETWEEN curdate() - INTERVAL ? DAY AND curdate()
            GROUP BY 1
            ORDER BY 1
            ", $days
        );
        $tasks = $this->constructAxes($this->db->to_array(false, MYSQLI_ASSOC, false), 'name', ['duration_avg', 'processed_avg'], false);

        $totals = $this->db->rowAssoc("
            SELECT count(pth.periodic_task_history_id) AS runs,
                   sum(pth.duration_ms) AS duration,
                   sum(pth.num_items) AS processed,
                   count(pthe.periodic_task_history_event_id) AS events,
                   sum(pth.num_errors) AS errors
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            LEFT JOIN periodic_task_history_event pthe USING (periodic_task_history_id)
            WHERE pt.is_enabled IS TRUE
              AND pth.launch_time BETWEEN curdate() - INTERVAL ? DAY AND curdate()
            ", $days
        );

        return [
            'hourly' => $hourly,
            'daily'  => $daily,
            'tasks'  => $tasks,
            'totals' => $totals,
        ];
    }

    public function getTaskRuntimeStats(int $taskId, int $days = 28) {
        $this->db->prepared_query("
            SELECT date(pth.launch_time) AS date,
                   sum(pth.duration_ms) AS duration,
                   sum(pth.num_items) AS processed
                   FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pt.periodic_task_id = ?
              AND pth.launch_time BETWEEN curdate() - INTERVAL ? DAY AND curdate()
            GROUP BY 1
            ORDER BY 1
            ", $taskId, $days
        );

        return $this->constructAxes($this->db->to_array(false, MYSQLI_ASSOC), 'date', ['duration', 'processed'], true);
    }

    public function getTaskSnapshot(float $start, float $end) {
        $this->db->prepared_query('
            SELECT pt.periodic_task_id, pt.name, pth.launch_time, pth.status, pth.num_errors, pth.num_items, pth.duration_ms
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pth.launch_time <= ? AND pth.launch_time + INTERVAL pth.duration_ms / 1000 SECOND >= ?
            ', $end, $start
        );

        return $this->db->to_array('periodic_task_id', MYSQLI_ASSOC);
    }

    public function run() {
        $phinxBinary = realpath(__DIR__ . '/../../vendor/bin/phinx');
        $phinxScript = realpath(__DIR__ . '/../../phinx.php');
        $pendingMigrations = array_filter(json_decode(shell_exec($phinxBinary . ' status -c '
            . $phinxScript . ' --format=json | tail -n 1'), true)['migrations'],
                function($value) { return count($value) > 0 && $value['migration_status'] === 'down'; });

        if (count($pendingMigrations)) {
            Irc::sendChannel('Pending migrations found, scheduler cannot continue', LAB_CHAN);
            echo "Pending migrations found, aborting\n";
            return;
        }

        $this->db->prepared_query('
            SELECT pt.periodic_task_id
            FROM periodic_task pt
            WHERE pt.is_enabled IS TRUE
                AND NOT EXISTS (
                    SELECT 1
                    FROM periodic_task_history pth
                    WHERE pth.periodic_task_id = pt.periodic_task_id
                        AND now() < (pth.launch_time + INTERVAL ((pth.duration_ms / 1000) + pt.period) SECOND)
                ) OR pt.run_now IS TRUE
        ');

        $toRun = $this->db->collect('periodic_task_id');

        foreach ($toRun as $id) {
            $this->runTask($id);
        }
    }

    public function runTask(int $id, bool $debug = false) {
        $task = $this->getTask($id);
        if ($task === null) {
            return;
        }
        echo('Running task '.$task['name']."...");

        $taskRunner = $this->createRunner($id, $task['name'], $task['classname'], $task['is_debug'] || $debug);
        if ($taskRunner === null) {
            Irc::sendChannel('Failed to construct task '.$task['name'], LAB_CHAN);
            return;
        }

        $taskRunner->begin();
        try {
            $taskRunner->run();
        } catch (\Throwable $e) {
            $taskRunner->log('Caught exception: ' . str_replace(SERVER_ROOT, '', $e->getMessage()), 'error');
        } finally {
            $taskRunner->end($task['is_sane']);
        }

        if ($task['run_now']) {
            $this->db->prepared_query('
                UPDATE periodic_task
                SET run_now = FALSE
                WHERE periodic_task_id = ?
                ', $id
            );
            $this->clearCache();
        }
    }

    private function createRunner(int $id, string $name, string $class, bool $isDebug): Task {
        $class = 'Gazelle\\Schedule\\Tasks\\'.$class;
        if (!class_exists($class)) {
            return null;
        }
        return new $class($id, $name, $isDebug);
    }
}
