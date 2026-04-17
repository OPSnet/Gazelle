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

    public function begin(): void {
        self::$db->prepared_query('
            INSERT INTO periodic_task_history
                   (periodic_task_id)
            VALUES (?)
            ', $this->taskId
        );
        $this->historyId = self::$db->inserted_id();
    }

    public function end(bool $sane): int {
        $elapsed = (hrtime(true) - $this->startTime) / 1e6;
        $errorCount = count(array_filter($this->events, fn($event) => $event->severity === 'error'));
        self::$db->prepared_query('
            UPDATE periodic_task_history SET
                status = ?,
                num_errors = ?,
                num_items = ?,
                duration_ms = ?
            WHERE periodic_task_history_id = ?
            ', 'completed', $errorCount, $this->processed, $elapsed, $this->historyId
        );

        echo("DONE! (" . number_format((hrtime(true) - $this->startTime) * 1e-9, 3) . ")\n");

        foreach ($this->events as $event) {
            printf("%s [%s] (%d) %s\n", $event->timestamp, $event->severity, $event->reference, $event->event);
            self::$db->prepared_query('
                INSERT INTO periodic_task_history_event
                       (periodic_task_history_id, severity, event_time, event,             reference)
                VALUES (?,                        ?,        ?,          substr(?, 1, 255), ?)
            ', $this->historyId, $event->severity, $event->timestamp, $event->event, $event->reference);
        }

        if ($errorCount > 0 && $sane) {
            self::$db->prepared_query('
                UPDATE periodic_task SET
                    is_sane = FALSE
                WHERE periodic_task_id = ?
            ', $this->taskId);
            self::$cache->delete_value(TaskScheduler::CACHE_TASKS);

            Irc::sendMessage(
                IRC_CHAN_DEV,
                "Task {$this->name} is no longer sane " . SITE_URL . "/tools.php?action=periodic&mode=detail&id={$this->taskId}"
            );
        } elseif ($errorCount == 0 && !$sane) {
            self::$db->prepared_query('
                UPDATE periodic_task SET
                    is_sane = TRUE
                WHERE periodic_task_id = ?
            ', $this->taskId);
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
