<?php

namespace Gazelle\Schedule;

use \Gazelle\Util\Irc;

abstract class Task extends \Gazelle\Base {
    protected $taskId;
    protected $name;
    protected $isDebug;
    protected $startTime;
    protected $historyId;

    protected $events;
    protected $processed;

    public function __construct(int $taskId, string $name, bool $isDebug) {
        $this->taskId = $taskId;
        $this->name = $name;
        $this->isDebug = $isDebug;
        $this->events = [];
        $this->processed = 0;
    }

    public function begin() {
        $this->startTime = microtime(true);
        self::$db->prepared_query('
            INSERT INTO periodic_task_history
                   (periodic_task_id)
            VALUES (?)
        ', $this->taskId);

        $this->historyId = self::$db->inserted_id();
    }

    public function end(bool $sane): int {
        $elapsed = (microtime(true) - $this->startTime) * 1000;
        $errorCount = count(array_filter($this->events, function ($event) { return $event->severity === 'error'; }));
        self::$db->prepared_query('
            UPDATE periodic_task_history
            SET status = ?,
                num_errors = ?,
                num_items = ?,
                duration_ms = ?
            WHERE periodic_task_history_id = ?
            ', 'completed', $errorCount, $this->processed, $elapsed, $this->historyId
        );

        echo("DONE! (".number_format(microtime(true) - $this->startTime, 3).")\n");

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
                UPDATE periodic_task
                SET is_sane = FALSE
                WHERE periodic_task_id = ?
            ', $this->taskId);
            self::$cache->delete_value(Scheduler::CACHE_TASKS);

            Irc::sendMessage(LAB_CHAN, 'Task ' . $this->name . ' is no longer sane ' . SITE_URL . '/tools.php?action=periodic&mode=detail&id=' . $this->taskId);
            // todo: send notifications to appropriate users
        } else if ($errorCount == 0 && !$sane) {
            self::$db->prepared_query('
                UPDATE periodic_task
                SET is_sane = TRUE
                WHERE periodic_task_id = ?
            ', $this->taskId);
            self::$cache->delete_value(Scheduler::CACHE_TASKS);

            Irc::sendMessage(LAB_CHAN, 'Task ' . $this->name . ' is now sane');
        }
        return $this->processed;
    }

    public function log(string $message, string $severity = 'info', int $reference = 0) {
        if (!$this->isDebug && $severity === 'debug') {
            return;
        }
        $this->events[] = new Event($severity, $message, $reference);
    }

    public function debug(string $message, int $reference = 0) {
        $this->log($message, 'debug', $reference);
    }

    public function info(string $message, int $reference = 0) {
        $this->log($message, 'info', $reference);
    }

    public function error(string $message, int $reference = 0) {
        $this->log($message, 'error', $reference);
    }

    abstract public function run();
}
