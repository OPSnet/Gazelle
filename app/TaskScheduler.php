<?php

namespace Gazelle;

use Gazelle\Util\Irc;

class TaskScheduler extends Base {
    final const CACHE_TASKS = 'scheduled_tasks';

    public function getTask(int $id): ?array {
        $tasks = $this->getTasks();
        return array_key_exists($id, $tasks) ? $tasks[$id] : null;
    }

    public function getTasks(): array {
        if (!$tasks = self::$cache->get_value(self::CACHE_TASKS)) {
            self::$db->prepared_query('
                SELECT periodic_task_id, name, classname, description, period, is_enabled, is_sane, is_debug, run_now
                FROM periodic_task
            ');

            $tasks = self::$db->to_array('periodic_task_id', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::CACHE_TASKS, $tasks, 3600);
        }

        return $tasks;
    }

    public function getInsaneTasks(): int {
        return count(array_filter($this->getTasks(),
            fn($v) => !$v['is_sane']
        ));
    }

    public static function isClassValid(string $class): bool {
        $class = 'Gazelle\\Task\\' . $class;
        return class_exists($class);
    }

    public function flush(): static {
        self::$cache->delete_value(self::CACHE_TASKS);
        return $this;
    }

    public function createTask(string $name, string $class, string $description, int $period, bool $isEnabled, bool $isSane, bool $isDebug): void {
        if (!self::isClassValid($class)) {
            return;
        }

        self::$db->prepared_query("
            INSERT INTO periodic_task
                   (name, classname, description, period, is_enabled, is_sane, is_debug)
            VALUES
                   (?,    ?,         ?,           ?,      ?,          ?,       ?)
        ", $name, $class, $description, $period, $isEnabled, $isSane, $isDebug);
        $this->flush();
    }

    public function updateTask(int $id, string $name, string $class, string $description, int $period, bool $isEnabled, bool $isSane, bool $isDebug): void {
        if (!self::isClassValid($class)) {
            return;
        }

        self::$db->prepared_query("
            UPDATE periodic_task SET
                name = ?,
                classname = ?,
                description = ?,
                period = ?,
                is_enabled = ?,
                is_sane = ?,
                is_debug = ?
            WHERE periodic_task_id = ?
        ", $name, $class, $description, $period, $isEnabled ? 1 : 0, $isSane ? 1 : 0, $isDebug ? 1 : 0, $id);
        $this->flush();
    }

    public function runNow(int $id): void {
        self::$db->prepared_query("
            UPDATE periodic_task SET
                run_now = 1 - run_now
            WHERE periodic_task_id = ?
            ", $id
        );
        $this->flush();
    }

    public function deleteTask(int $id): void {
        self::$db->prepared_query("
            DELETE FROM periodic_task WHERE periodic_task_id = ?
        ", $id);
        $this->flush();
    }

    public function getTaskDetails(int $days = 7): array {
        self::$db->prepared_query("
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

        return self::$db->to_array('periodic_task_id', MYSQLI_ASSOC, false);
    }

    public function getTotal(int $id): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM periodic_task_history WHERE periodic_task_id = ?
            ", $id
        );
    }

    public function getTaskHistory(int $id, int $limit, int $offset, string $sort, string $direction): ?TaskScheduler\TaskHistory {
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

        self::$db->prepared_query("
            SELECT periodic_task_history_id, launch_time, status, num_errors, num_items, duration_ms
            FROM periodic_task_history
            WHERE periodic_task_id = ?
            ORDER BY $sort $direction
            LIMIT ? OFFSET ?
        ", $id, $limit, $offset);
        $items = self::$db->to_array('periodic_task_history_id', MYSQLI_ASSOC);

        $historyEvents = [];
        if (count($items)) {
            self::$db->prepared_query("
                SELECT periodic_task_history_id, event_time, severity, event, reference
                FROM periodic_task_history_event
                WHERE periodic_task_history_id IN (" . placeholders($items) . ")
                ORDER BY event_time, periodic_task_history_event_id
            ", ...array_keys($items));
            $events = self::$db->to_array(false, MYSQLI_ASSOC);

            foreach ($events as $event) {
                [$historyId, $eventTime, $severity, $message, $reference] = array_values($event);
                $historyEvents[$historyId][] = new TaskScheduler\Event($severity, $message, $reference, $eventTime);
            }
        }

        $task = new TaskScheduler\TaskHistory($this->getTask($id)['name'], $this->getTotal($id));
        foreach ($items as $item) {
            [$historyId, $launchTime, $status, $numErrors, $numItems, $duration] = array_values($item);
            $taskEvents = $historyEvents[$historyId] ?? [];
            $task->items[] = new TaskScheduler\HistoryItem($launchTime, $status, $numErrors, $numItems, $duration, $taskEvents);
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
                    fn($v) => [$time ? strtotime($v[$key]) * 1000 : $v[$key], (int)$v[$id]],
                    $data
                )
            ];
        }
        return $result;
    }

    public function getRuntimeStats(int $days = 28): array {
        self::$db->prepared_query("
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
        $hourly = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC, false), 'date', ['duration', 'processed'], true);

        self::$db->prepared_query("
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
        $daily = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC, false), 'date', ['duration', 'processed'], true);

        self::$db->prepared_query("
            SELECT pt.name,
                avg(pth.duration_ms) AS duration_avg,
                avg(pth.num_items)   AS processed_avg
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pt.is_enabled IS TRUE
                AND pth.launch_time BETWEEN curdate() - INTERVAL ? DAY AND curdate()
            GROUP BY 1
            ORDER BY 1
            ", $days
        );
        $tasks = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC, false), 'name', ['duration_avg', 'processed_avg'], false);

        $totals = self::$db->rowAssoc("
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

    public function getTaskRuntimeStats(int $taskId, int $days = 28): array {
        self::$db->prepared_query("
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

        return $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC, false), 'date', ['duration', 'processed'], true);
    }

    public function getTaskSnapshot(float $start, float $end): array {
        self::$db->prepared_query('
            SELECT pt.periodic_task_id, pt.name, pth.launch_time, pth.status, pth.num_errors, pth.num_items, pth.duration_ms
            FROM periodic_task pt
            INNER JOIN periodic_task_history pth USING (periodic_task_id)
            WHERE pth.launch_time <= ? AND pth.launch_time + INTERVAL pth.duration_ms / 1000 SECOND >= ?
            ', $end, $start
        );

        return self::$db->to_array('periodic_task_id', MYSQLI_ASSOC, false);
    }

    public function run(): void {
        $phinxBinary = realpath(__DIR__ . '/../vendor/bin/phinx');
        $phinxScript = realpath(__DIR__ . '/../phinx.php');
        $pendingMigrations = array_filter(json_decode((string)shell_exec($phinxBinary . ' status -c '
            . $phinxScript . ' --format=json | tail -n 1'), true)['migrations'],
                fn($value) => count($value) > 0 && $value['migration_status'] === 'down');

        if (count($pendingMigrations)) {
            Irc::sendMessage(LAB_CHAN, 'Pending migrations found, scheduler cannot continue');
            echo "Pending migrations found, aborting\n";
            return;
        }

        /**
         * We attempt to run as many tasks as we can within a minute. If a task
         * runs over the TTL, it will be noted as in progress, so the next
         * invocation of the scheduler will ignore it. When the task finally
         * returns, this invocation will exit.
         * If a task fails, do not try to run again in this slice.
         */
        $fail = [0];

        $TTL = microtime(true) + 58;
        while (microtime(true) < $TTL) {
            $taskId = (int)self::$db->scalar("
                SELECT pt.periodic_task_id
                FROM periodic_task pt
                LEFT JOIN (
                    SELECT pth.periodic_task_id,
                    max(pth.launch_time) AS launch_time
                    FROM periodic_task_history pth
                    WHERE pth.status = 'completed'
                    GROUP BY pth.periodic_task_id
                ) last USING (periodic_task_id)
                WHERE pt.is_enabled IS TRUE
                    AND pt.is_sane IS TRUE
                    AND (
                        last.periodic_task_id is null
                        OR last.launch_time + INTERVAL pt.period SECOND < now()
                        OR pt.run_now IS TRUE
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM periodic_task_history r
                        WHERE r.status = 'running'
                            AND r.periodic_task_id = pt.periodic_task_id
                    )
                    AND pt.periodic_task_id NOT IN (" . placeholders($fail) . ")
                LIMIT 1
                ", ...$fail
            );
            if (!$taskId) {
                break;
            }
            $result = $this->runTask($taskId);
            if ($result == -1) {
                $fail[] = $taskId;
            }
        }
    }

    public function runClass(string $className, bool $debug = false): int {
        return $this->runTask(
            (int)self::$db->scalar("
                SELECT pt.periodic_task_id FROM periodic_task pt WHERE pt.classname = ?
                ", $className
            ), $debug
        );
    }

    public function runTask(int $id, bool $debug = false): int {
        $task = $this->getTask($id);
        if ($task === null) {
            return -1;
        }
        echo('Running task ' . $task['name'] . "...");

        $taskRunner = $this->createRunner($id, $task['name'], $task['classname'], $task['is_debug'] || $debug);
        if ($taskRunner === null) {
            Irc::sendMessage(LAB_CHAN, 'Failed to construct task ' . $task['name']);
            return -1;
        }

        $processed = -1;
        $taskRunner->begin();
        try {
            $taskRunner->run();
        } catch (\Throwable $e) {
            $taskRunner->log('Caught exception: ' . str_replace(SERVER_ROOT, '', $e->getMessage()), 'error');
        } finally {
            $processed = $taskRunner->end($task['is_sane']);
        }

        if ($task['run_now']) {
            self::$db->prepared_query('
                UPDATE periodic_task SET
                    run_now = FALSE
                WHERE periodic_task_id = ?
                ', $id
            );
            $this->flush();
        }
        return $processed;
    }

    private function createRunner(int $id, string $name, string $class, bool $isDebug): mixed {
        $class = 'Gazelle\\Task\\' . $class;
        if (!class_exists($class)) {
            return null;
        }
        return new $class($id, $name, $isDebug);
    }
}
