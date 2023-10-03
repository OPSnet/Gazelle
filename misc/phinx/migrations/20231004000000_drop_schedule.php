<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropSchedule extends AbstractMigration {
    public function up(): void {
        $this->table('schedule')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE schedule (
              schedule_id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              NextHour int NOT NULL DEFAULT '0',
              NextDay int NOT NULL DEFAULT '0',
              NextBiWeekly int NOT NULL DEFAULT '0'
            )
        ");
    }
}
