<?php

namespace Gazelle;

use Gazelle\Util\Irc;

abstract class Task extends Base {
    protected array $events = [];
    protected int $processed = 0;
    protected int $historyId;
    protected float $startTime;

    abstract public function run(): void;

    public function __construct(
        public readonly int $taskId,
        public readonly string $name,
        public readonly bool $isDebug,
    ) {
        $this->startTime = hrtime(true);
    }

    public function begin(): int {
        $this->historyId = $this->pg()->scalarex('
            insert into task_history
                   (id_task)
            values ($1)
            returning id_task_history
            ', $this->taskId
        );
        return $this->historyId;
    }

    public function end(bool $sane): int {
        $elapsed = (hrtime(true) - $this->startTime) / 1e6;
        $errorCount = count(array_filter($this->events, fn($event) => $event->severity === 'error'));
        $this->pg()->query('
            update task_history set
                status      = $1::task_status_t,
                error_total = $2,
                item_total  = $3,
                duration_ms = $4
            where id_task_history = $5
            ', 'completed', $errorCount, $this->processed, (int)$elapsed, $this->historyId
        );

        echo("DONE! (" . number_format((hrtime(true) - $this->startTime) * 1e-9, 3) . ")\n");

        foreach ($this->events as $event) {
            $timestamp = $event->timestamp()->format('Y-m-d H:i:s');
            printf("%s [%s] (%d) %s\n", $timestamp, $event->severity, $event->reference, $event->event);
            $this->pg()->query('
                insert into task_history_event
                       (id_task_history, created, reference, severity,                  note)
                VALUES ($1,              $2,      $3,        $4::task_event_severity_t, substr($5, 1, 1000))
            ', $this->historyId, $timestamp, $event->reference, $event->severity, $event->event);
        }

        if ($errorCount > 0 && $sane) {
            $this->pg()->query('
                update task set
                    is_sane = false
                where id_task = $1
                ', $this->taskId
            );
            self::$cache->delete_value(TaskScheduler::CACHE_TASKS);
            Irc::sendMessage(
                IRC_CHAN_DEV,
                "Task {$this->name} is no longer sane "
                    . SITE_URL
                    . "/tools.php?action=periodic&mode=detail&id={$this->taskId}"
            );
        } elseif ($errorCount === 0 && !$sane) {
            $this->pg()->query('
                update task set
                    is_sane = true
                where id_task = $1
                ', $this->taskId
            );
            self::$cache->delete_value(TaskScheduler::CACHE_TASKS);
            Irc::sendMessage(IRC_CHAN_DEV, "Task {$this->name} is now sane");
        }
        return $this->processed;
    }

    public function log(string $message, string $severity = 'info', int $reference = 0): void {
        if (!$this->isDebug && $severity === 'debug') {
            return;
        }
        $this->events[] = new TaskScheduler\Event($severity, $message, $reference);
    }

    public function debug(string $message, int $reference = 0): void {
        $this->log($message, 'debug', $reference);
    }

    public function info(string $message, int $reference = 0): void {
        $this->log($message, 'info', $reference);
    }

    public function error(string $message, int $reference = 0): void {
        $this->log($message, 'error', $reference);
    }
}
