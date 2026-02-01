<?php

namespace Gazelle;

/* Note: tasks are created and removed via a phinx migration. There
 * is no ability to create a task from the site because the task
 * code will need to be added via a repo commit in any event.
 */

class TaskScheduler extends Base {
    final public const CACHE_TASKS = 'scheduled_tasks';

    protected Util\SortableTableHeader $heading;

    public function findById(int $taskId): ?array {
        $tasks = $this->taskList();
        return array_key_exists($taskId, $tasks) ? $tasks[$taskId] : null;
    }

    public function findByName(string $className): ?array {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT pt.periodic_task_id FROM periodic_task pt WHERE pt.classname = ?
                ", $className
            )
        );
    }

    public function taskList(): array {
        self::$db->prepared_query("
            SELECT periodic_task_id, name, classname, description, period, is_enabled, is_sane, is_debug, run_now
            FROM periodic_task
        ");
        return self::$db->to_array('periodic_task_id', MYSQLI_ASSOC);
    }

    public function insaneTaskList(): int {
        return count(array_filter($this->taskList(),
            fn ($v) => !$v['is_sane']
        ));
    }

    public static function className(string $class): string {
        return "Gazelle\\Task\\$class";
    }

    public function isClassValid(string $class): bool {
        return class_exists(self::className($class));
    }

    public function enqueue(int $taskId): int {
        self::$db->prepared_query("
            UPDATE periodic_task SET
                is_sane = true,
                run_now = true
            WHERE periodic_task_id = ?
            ", $taskId
        );
        return self::$db->affected_rows();
    }

    public function clear(int $taskId): int {
        self::$db->prepared_query('
            UPDATE periodic_task SET
                run_now = false
            WHERE periodic_task_id = ?
            ', $taskId
        );
        return self::$db->affected_rows();
    }

    public function updateTask(
        int $taskId,
        string $name,
        string $class,
        string $description,
        int $period,
        bool $isEnabled,
        bool $isSane,
        bool $isDebug
    ): int {
        if (!$this->isClassValid($class)) {
            return 0;
        }
        self::$db->prepared_query("
            UPDATE periodic_task SET
                name        = ?,
                classname   = ?,
                description = ?,
                period      = ?,
                is_enabled  = ?,
                is_sane     = ?,
                is_debug    = ?
            WHERE periodic_task_id = ?
            ", $name, $class, $description, $period,
                (int)$isEnabled, (int)$isSane, (int)$isDebug,
                $taskId,
        );
        return self::$db->affected_rows();
    }

    public function runClass(string $className, bool $debug = false): int {
        $task = $this->findByName($className);
        if ($task === null) {
            return -1;
        }
        return $this->runTask($task['periodic_task_id'], $debug);
    }

    public function runNow(int $taskId): int {
        self::$db->prepared_query("
            UPDATE periodic_task SET
                run_now = 1 - run_now
            WHERE periodic_task_id = ?
            ", $taskId
        );
        return self::$db->affected_rows();
    }

    public function run(): int {
        $pendingMigrations = array_filter(
            json_decode(
                (string)shell_exec(BIN_PHINX . ' status -c ' . PHINX_MYSQL . ' --format=json | tail -n 1'),
                true
            )['migrations'],
            fn ($value) => count($value) > 0 && $value['migration_status'] === 'down'
        );

        if ($pendingMigrations) {
            Util\Irc::sendMessage(IRC_CHAN_DEV, 'Pending migrations found, scheduler cannot continue');
            echo "Pending migrations found, aborting\n";
            return 0;
        }

        /**
         * We attempt to run as many tasks as we can within a minute. If a task
         * runs over the TTL, it will be noted as in progress, so the next
         * invocation of the scheduler ignores it. When the task finally
         * returns, this invocation will exit.
         * If a task fails, do not try to run again in this slice.
         */

        $fail = [0];
        $run  = 0;

        $TTL = hrtime(true) + 58e9;
        while (hrtime(true) < $TTL) {
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
                // no tasks remaining to be run
                break;
            }
            $run++;
            $result = $this->runTask($taskId);
            if ($result == -1) {
                $fail[] = $taskId;
            }
        }
        return $run;
    }

    public function runTask(int $taskId, bool $debug = false): int {
        $task = $this->findById($taskId);
        if ($task === null) {
            return -1;
        }
        echo "Running task {$task['name']}...";

        $taskRunner = $this->createRunner($taskId, $task['name'], $task['classname'], $task['is_debug'] || $debug);
        if (is_null($taskRunner)) {
            echo "DONE! (0.000)\n";
            Util\Irc::sendMessage(IRC_CHAN_DEV, "Failed to construct task {$task['name']}");
            return -1;
        }

        // some tasks require a viewer context (and probably should not...)
        $sysop = new Manager\User()->findById(
            (int)self::$db->scalar('
                SELECT um.id
                FROM users_main um
                INNER JOIN permissions p on (p.ID = um.PermissionID)
                ORDER BY p.level DESC
                LIMIT 1
            ')
        );
        $this->requestContext()->setViewer($sysop);

        $processed = -1;
        $taskRunner->begin();
        try {
            $taskRunner->run();
        } catch (\Throwable $e) {
            $taskRunner->log('Caught exception: ' . str_replace(SERVER_ROOT, '', $e->getMessage()), 'error');
            global $Debug;
            $Debug->saveCase($e->getMessage());
        } finally {
            $processed = $taskRunner->end($task['is_sane']);
        }

        if ($task['run_now']) {
            $this->clear($taskId);
        }
        return $processed;
    }

    private function createRunner(int $taskId, string $name, string $class, bool $isDebug): mixed {
        if (!$this->isClassValid($class)) {
            return null;
        }
        $taskClass = self::className($class);
        return new $taskClass($taskId, $name, $isDebug);
    }

    public function taskRunTotal(int $taskId): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM periodic_task_history WHERE periodic_task_id = ?
            ", $taskId
        );
    }

    public function heading(): Util\SortableTableHeader {
        return $this->heading ??= new Util\SortableTableHeader(
            'next', [
                'name'        => ['dbColumn' => 'name',      'defaultSort' => 'asc',  'text' => 'Name'],
                'period'      => ['dbColumn' => 'period',    'defaultSort' => 'asc',  'text' => 'Interval'],
                'runs'        => ['dbColumn' => 'runs',      'defaultSort' => 'desc', 'text' => 'Runs'],
                'duration'    => ['dbColumn' => 'duration',  'defaultSort' => 'desc', 'text' => 'Duration'],
                'processed'   => ['dbColumn' => 'processed', 'defaultSort' => 'desc', 'text' => 'Processed'],
                'status'      => ['dbColumn' => 'status',    'defaultSort' => 'desc', 'text' => 'Status'],
                'errors'      => ['dbColumn' => 'errors',    'defaultSort' => 'desc', 'text' => 'Errors'],
                'events'      => ['dbColumn' => 'events',    'defaultSort' => 'desc', 'text' => 'Events'],
                'last'        => [
                    'dbColumn'    => 'last_run IS NULL ASC, is_enabled DESC, last_run',
                    'defaultSort' => 'desc',
                    'text'        => 'Last Run'
                ],
                'next'        => [
                    'dbColumn'    => 'next_run IS NULL ASC, is_enabled DESC, run_now DESC, next_run',
                    'defaultSort' => 'asc',
                    'text'        => 'Next Run'
                ],
            ]
        );
    }

    public function taskDetailList(int $days = 7): array {
        self::$db->prepared_query("
            SELECT pt.periodic_task_id,
                pt.period,
                pt.name,
                pt.description,
                pt.period,
                pt.is_enabled,
                pt.is_sane,
                pt.run_now,
                last_value(pth.status) OVER (PARTITION BY pt.periodic_task_id, pt.period)
                                                              AS status,
                count(DISTINCT pth.periodic_task_history_id)  AS runs,
                sum(DISTINCT pth.num_items)                   AS processed,
                sum(DISTINCT pth.num_errors)                  AS errors,
                sum(DISTINCT pth.duration_ms)                 AS duration,
                count(pthe.periodic_task_history_event_id)    AS events,
                max(pth.launch_time)                          AS last_run,
                max(pth.launch_time) + INTERVAL period SECOND AS next_run
            FROM periodic_task                    pt
            LEFT JOIN periodic_task_history       pth  USING (periodic_task_id)
            LEFT JOIN periodic_task_history_event pthe USING (periodic_task_history_id)
            WHERE (
                    pth.launch_time IS NULL
                    OR pth.launch_time > now() - INTERVAL ? DAY
                )
            GROUP BY pt.periodic_task_id,
                pt.period,
                pt.name,
                pt.description,
                pt.period,
                pt.is_enabled,
                pt.is_sane,
                pt.run_now
            ORDER BY {$this->heading()->orderBy()} {$this->heading()->dir()}, name ASC
            ", $days
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function taskHistory(
        int $taskId,
        int $limit,
        int $offset,
    ): ?TaskScheduler\TaskHistory {
        self::$db->prepared_query("
            SELECT periodic_task_history_id, launch_time, status, num_errors, num_items, duration_ms
            FROM periodic_task_history
            WHERE periodic_task_id = ?
            ORDER BY launch_time DESC
            LIMIT ? OFFSET ?
            ", $taskId, $limit, $offset
        );
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

        $history = new TaskScheduler\TaskHistory(
            $this->findById($taskId)['name'],
            $this->taskRunTotal($taskId)
        );
        foreach ($items as $item) {
            [$historyId, $launchTime, $status, $numErrors, $numItems, $duration] = array_values($item);
            $taskEvents = $historyEvents[$historyId] ?? [];
            $history->items[] = new TaskScheduler\HistoryItem($launchTime, $status, $numErrors, $numItems, $duration, $taskEvents);
        }

        return $history;
    }

    private function constructAxes(array $data, string $key, array $axes, bool $time): array {
        $result = [];

        foreach ($axes as $axis) {
            if (is_array($axis)) {
                $taskId = $axis[0];
                $name = $axis[1];
            } else {
                $taskId = $axis;
                $name = $axis;
            }

            $result[] = [
                'name' => $name,
                'data' => array_map(
                    fn ($v) => [$time ? (int)strtotime($v[$key]) * 1000 : $v[$key], (int)$v[$taskId]],
                    $data
                )
            ];
        }
        return $result;
    }

    public function runtimeStats(int $days = 90): array {
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
        $hourly = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC), 'date', ['duration', 'processed'], true);

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
        $daily = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC), 'date', ['duration', 'processed'], true);

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
        $tasks = $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC), 'name', ['duration_avg', 'processed_avg'], false);

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

    public function taskRuntimeStats(int $taskId, int $days = 90): array {
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

        return $this->constructAxes(self::$db->to_array(false, MYSQLI_ASSOC), 'date', ['duration', 'processed'], true);
    }
}
