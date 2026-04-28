<?php

namespace Gazelle;

/* Note: tasks are created and removed via a phinx migration. There
 * is no ability to create a task from the site because the task
 * code will need to be added via a repo commit in any event.
 */

class TaskScheduler extends Base {
    final public const CACHE_TASKS = 'tasks';

    protected Util\SortableTableHeader $heading;

    public function findById(int $taskId): ?array {
        $tasks = $this->taskList();
        return array_key_exists($taskId, $tasks) ? $tasks[$taskId] : null;
    }

    public function findByName(string $className): ?array {
        return $this->findById(
            (int)$this->pg()->scalarex("
                select id_task from task where class_name = $1
                ", $className
            )
        );
    }

    public function heading(): Util\SortableTableHeader {
        return $this->heading ??= new Util\SortableTableHeader(
            'next', [
                'name'        => ['dbColumn' => 'name',        'defaultSort' => 'asc',  'text' => 'Name'],
                'period'      => ['dbColumn' => 'period',      'defaultSort' => 'asc',  'text' => 'Interval'],
                'run_total'   => ['dbColumn' => 'run_total',   'defaultSort' => 'desc', 'text' => 'Runs'],
                'duration'    => ['dbColumn' => 'duration',    'defaultSort' => 'desc', 'text' => 'Duration'],
                'item_total'  => ['dbColumn' => 'item_total',  'defaultSort' => 'desc', 'text' => 'Processed'],
                'status'      => ['dbColumn' => 'status',      'defaultSort' => 'desc', 'text' => 'Status'],
                'error_total' => ['dbColumn' => 'error_total', 'defaultSort' => 'desc', 'text' => 'Errors'],
                'event_total' => ['dbColumn' => 'event_total', 'defaultSort' => 'desc', 'text' => 'Events'],
                'last'        => [
                    'dbColumn'    => 'last_run is null asc, is_enabled desc, last_run',
                    'defaultSort' => 'desc',
                    'text'        => 'Last Run'
                ],
                'next'        => [
                    'dbColumn'    => 'next_run is null asc, is_enabled desc, run_now desc, next_run',
                    'defaultSort' => 'asc',
                    'text'        => 'Next Run'
                ],
            ]
        );
    }

    public function taskDetailList(int $days = 7): array {
        return $this->pg()->allex(<<<END_SQL
            with events(id_task, total) as (
                select th.id_task,
                    count(*)
                from task_history_event the
                inner join task_history th using (id_task_history)
                where th.created > now() - interval '1 day' * $1
                group by th.id_task
            ),
            stats(id_task, id_task_history, run_total, error_total, item_total) as (
                select id_task,
                    max(id_task_history),
                    count(*),
                    sum(error_total),
                    sum(item_total)
                from task_history
                where created > now() - interval '1 day' * $1
                group by id_task
            )
            select * from (
                select t.id_task, t.name, t.description, t.period, t.is_enabled, t.is_sane, t.run_now,
                    coalesce(s.run_total, 0)    as run_total,
                    coalesce(s.item_total, 0)   as item_total,
                    coalesce(s.error_total, 0)  as error_total,
                    coalesce(e.total, 0)        as event_total,
                    coalesce(th.duration_ms, 0) as duration,
                    th.status,
                    th.created                  as last_run,
                    th.created + interval '1 second' * t.period
                                                as next_run
                from task              t
                left join events       e  on (e.id_task = t.id_task)
                left join stats        s  on (s.id_task = t.id_task)
                left join task_history th using (id_task_history)
            ) q
            order by {$this->heading()->orderBy()} {$this->heading()->dir()}, name asc
            END_SQL, $days
        );
        // need to wrap the select so that pg can resolve next_run
    }

    public function taskList(): array {
        return $this->pg()->allByKeyex(
            'id_task',
            <<<END_SQL
            select id_task,
                name,
                class_name,
                description,
                period,
                is_enabled,
                is_sane,
                is_debug,
                run_now
            from task
            order by class_name
            END_SQL
        );
    }

    public function insaneTaskTotal(): int {
        return count(array_filter(
            $this->taskList(),
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
        return $this->pg()->query('
            update task set
                is_sane = true,
                run_now = true
            where id_task = $1
            ', $taskId
        );
    }

    public function clear(int $taskId): int {
        return $this->pg()->query('
            update task set
                run_now = false
            where id_task = $1
            ', $taskId
        );
    }

    public function updateTask(
        int $taskId,
        string $name,
        string $description,
        int $period,
        bool $isEnabled,
        bool $isSane,
        bool $isDebug
    ): int {
        $affected = $this->pg()->query('
            update task set
                name        = $1,
                description = $2,
                period      = $3,
                is_enabled  = $4,
                is_sane     = $5,
                is_debug    = $6
            where id_task = $7
            ', $name, $description, $period, $isEnabled, $isSane, $isDebug,
                $taskId,
        );
        self::$cache->delete_value(self::CACHE_TASKS);
        return $affected;
    }

    public function runClass(string $className, bool $debug = false): int {
        $task = $this->findByName($className);
        if ($task === null) {
            return -1;
        }
        return $this->runTask($task['id_task'], $debug);
    }

    public function runNow(int $taskId): int {
        return $this->pg()->query("
            update task set
                run_now = true
            where id_task = $1
            ", $taskId
        );
    }

    public function run(): int {
        if (new SiteInfo()->phinxMigrationsPending()) {
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
            $taskId = (int)$this->pg()->scalarex("
                select t.id_task
                from task t
                left join (
                    select th.id_task,
                    max(th.created) AS created
                    from task_history th
                    where th.status = 'completed'
                    group by th.id_task
                ) last using (id_task)
                where t.is_enabled is true
                    and t.is_sane is true
                    and (
                        last.id_task is null
                        or last.created + interval '1 second' * t.period < now()
                        or t.run_now is true
                    )
                    and not exists (
                        select 1 from task_history r
                        where r.status = 'running'
                            anD r.id_task = t.id_task
                    )
                    and not t.id_task = any($1::integer[])
                limit 1
                ", $this->pg()->integerConverter()->output($fail)
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

        $taskRunner = $this->createRunner($taskId, $task['name'], $task['class_name'], $task['is_debug'] || $debug);
        if (is_null($taskRunner)) {
            echo "Failed.\n";
            Util\Irc::sendMessage(
                IRC_CHAN_DEV,
                sprintf("No implementation for task $taskId (%s), disabling.", self::className($task['class_name']))
            );
            $this->pg()->query('
                update task set
                    is_enabled = false
                where id_task = $1
                ', $taskId
            );
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
        return (int)$this->pg()->scalarex("
            select count(*) from task_history where id_task = $1
            ", $taskId
        );
    }

    public function taskHistory(
        int $taskId,
        int $limit,
        int $offset,
    ): ?TaskScheduler\TaskHistory {
        $items = $this->pg()->allex('
            select id_task_history, created, status, error_total, item_total, duration_ms
            from task_history
            where id_task = $1
            order by created desc
            limit $2 offset $3
            ', $taskId, $limit, $offset
        );

        $historyEvents = [];
        if ($items !== []) {
            $events = $this->pg()->allex('
                select id_task_history, created, severity, note, reference
                from task_history_event
                where id_task_history = any($1::integer[])
                order by created, id_task_history_event
                ', $this->pg()->integerConverter()->output(
                    array_column($items, 'id_task_history')
                )
            );
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
            [$historyId, $created, $status, $errorTotal, $itemTotal, $duration] = array_values($item);
            $taskEvents = $historyEvents[$historyId] ?? [];
            $history->items[] = new TaskScheduler\HistoryItem($created, $status, $errorTotal, $itemTotal, $duration, $taskEvents);
        }

        return $history;
    }

    public function runtimeStats(int $days = 90): array {
        $daily = $this->pg()->rowAssocex(<<<END_SQL
            with daily(ymd, name, duration, item_total) as (
                select to_char(th.created, 'YYYY-MM-DD') as ymd,
                    t.name,
                    sum(th.duration_ms),
                    sum(th.item_total)
                from task               t
                inner join task_history th using (id_task)
                where t.is_enabled is true
                    and th.created between now()::date - interval '1 day' * $1 and now()::date
                group by ymd, t.name
                order by ymd, t.name
            ),
            by_day(duration, item_total) as (
                select sum(duration), sum(item_total) from daily group by ymd order by ymd
            ),
            by_task(duration, item_total) as (
                select sum(duration), sum(item_total) from daily group by name order by name
            )
            select
                (select jsonb_agg(distinct ymd)  from daily)   as daily_category,
                (select jsonb_agg(duration)      from by_day)  as daily_duration,
                (select jsonb_agg(item_total)    from by_day)  as daily_total,
                (select jsonb_agg(distinct name) from daily)   as task_category,
                (select jsonb_agg(duration)      from by_task) as task_duration,
                (select jsonb_agg(item_total)    from by_task) as task_total
            END_SQL, $days
        );

        return [
            'hourly' => $this->pg()->rowAssocex(<<<END_SQL
                with hourly(h, duration, item_total) as (
                    select to_char(th.created, 'HH24:00') as h,
                        sum(th.duration_ms),
                        sum(th.item_total)
                    from task               t
                    inner join task_history th using (id_task)
                    where th.created > now() - interval '1 day'
                    group by h
                    order by h
                )
                select
                    jsonb_agg(h)          as category,
                    jsonb_agg(duration)   as duration,
                    jsonb_agg(item_total) as item_total
                from hourly
                END_SQL
            ),
            'daily' => [
                'category'   => $daily['daily_category'],
                'duration'   => $daily['daily_duration'],
                'item_total' => $daily['daily_total'],
            ],
            'tasks' => [
                'category'   => $daily['task_category'],
                'duration'   => $daily['task_duration'],
                'item_total' => $daily['task_total'],
            ],
            'totals' => $this->pg()->rowAssocex(<<<END_SQL
                select count(*)                      as run_total,
                    sum(th.duration_ms)              as duration,
                    sum(th.item_total)               as item_total,
                    count(the.id_task_history_event) as event_total,
                    sum(th.error_total)              as error_total
                from task                    t
                inner join task_history      th using (id_task)
                left join task_history_event the using (id_task_history)
                where t.is_enabled is true
                    and th.created between now()::date - interval '1 day' * $1 and now()::date
                END_SQL, $days
            ),
        ];
    }

    public function taskRuntimeStats(int $taskId, int $days = 90): array {
        return $this->pg()->rowAssocex(<<<END_SQL
            with daily(ymd, name, duration, item_total) as (
                select to_char(th.created, 'YYYY-MM-DD') as ymd,
                    t.name,
                    sum(th.duration_ms),
                    sum(th.item_total)
                from task               t
                inner join task_history th using (id_task)
                where t.id_task = $1
                    and th.created between now()::date - interval '1 day' * $2 and now()::date
                group by ymd, t.name
                order by ymd, t.name
            ),
            by_day(duration, item_total) as (
                select sum(duration), sum(item_total) from daily group by ymd order by ymd
            ),
            by_task(duration, item_total) as (
                select sum(duration), sum(item_total) from daily group by name order by name
            )
            select
                (select jsonb_agg(distinct ymd) from daily)  as category,
                (select jsonb_agg(duration)     from by_day) as duration,
                (select jsonb_agg(item_total)   from by_day) as item_total
            END_SQL, $taskId, $days
        );
    }

    public function isLastRunIntervalOk(int $delay): bool {
        return $delay >= (int)$this->pg()->scalarex("
            select extract(epoch from now() - max(created)) from task_history
        ");
    }
}
