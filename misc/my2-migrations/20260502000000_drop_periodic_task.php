<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropPeriodicTask extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task_history_event')->drop()->save();
        $this->table('periodic_task_history')->drop()->save();
        $this->table('periodic_task')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE periodic_task (
                periodic_task_id int NOT NULL AUTO_INCREMENT,
                name varchar(64) NOT NULL,
                classname varchar(32) NOT NULL,
                description varchar(255) NOT NULL,
                period int NOT NULL,
                is_enabled tinyint(1) NOT NULL DEFAULT '1',
                is_sane tinyint(1) NOT NULL DEFAULT '1',
                is_debug tinyint(1) NOT NULL DEFAULT '0',
                run_now tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (periodic_task_id),
                UNIQUE KEY name (name),
                UNIQUE KEY classname (classname)
            )
        ");
        $this->query("
            CREATE TABLE periodic_task_history (
                periodic_task_history_id int NOT NULL AUTO_INCREMENT,
                periodic_task_id int NOT NULL,
                launch_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status enum('running','completed','failed') NOT NULL DEFAULT 'running',
                num_errors int NOT NULL DEFAULT '0',
                num_items int NOT NULL DEFAULT '0',
                duration_ms int NOT NULL DEFAULT '0',
                PRIMARY KEY (periodic_task_history_id),
                KEY pth_lt_idx (launch_time),
                KEY periodic_task_id (periodic_task_id),
                CONSTRAINT periodic_task_history_ibfk_1 FOREIGN KEY (periodic_task_id) REFERENCES periodic_task (periodic_task_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ");
        $this->query("
            CREATE TABLE periodic_task_history_event (
                periodic_task_history_event_id int NOT NULL AUTO_INCREMENT,
                periodic_task_history_id int NOT NULL,
                severity enum('debug','info','error') NOT NULL,
                event_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                event varchar(255) NOT NULL,
                reference int NOT NULL,
                PRIMARY KEY (periodic_task_history_event_id),
                KEY periodic_task_history_id (periodic_task_history_id),
                CONSTRAINT periodic_task_history_event_ibfk_1 FOREIGN KEY (periodic_task_history_id) REFERENCES periodic_task_history (periodic_task_history_id) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ");
    }
}
