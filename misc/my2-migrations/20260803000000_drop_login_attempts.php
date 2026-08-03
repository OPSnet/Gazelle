<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropLoginAttempts extends AbstractMigration {
    public function up(): void {
        $this->table('login_attempts')->drop()->save();
    }

    public function down(): void {
        $this->query('
            CREATE TABLE login_attempts (
                ID int NOT NULL AUTO_INCREMENT,
                UserID int NOT NULL DEFAULT 0,
                IP varchar(15) NOT NULL,
                LastAttempt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                Attempts int NOT NULL DEFAULT 0,
                BannedUntil datetime DEFAULT NULL,
                Bans int NOT NULL DEFAULT 0,
                capture varchar(80) DEFAULT NULL,
                PRIMARY KEY (ID),
                KEY IP (IP),
                KEY attempts_idx (Attempts)
            )
        ');
    }
}
