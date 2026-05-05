<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUsersHistoryIps extends AbstractMigration {
    public function up(): void {
        $this->table('users_history_ips')->drop()->save();
        $this->table('ip_bans')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE `users_history_ips` (
              `UserID` int NOT NULL,
              `IP` varchar(15) NOT NULL DEFAULT '0.0.0.0',
              `StartTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `EndTime` datetime DEFAULT NULL,
              PRIMARY KEY (`UserID`,`IP`,`StartTime`),
              KEY `IP` (`IP`),
              KEY `EndTime` (`EndTime`)
            )
        ");
        $this->query("
            CREATE TABLE `ip_bans` (
              `ID` int unsigned NOT NULL AUTO_INCREMENT,
              `FromIP` int unsigned NOT NULL,
              `ToIP` int unsigned NOT NULL,
              `Reason` varchar(255) DEFAULT NULL,
              `user_id` int unsigned NOT NULL DEFAULT '0',
              `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`ID`),
              UNIQUE KEY `FromIP_2` (`FromIP`,`ToIP`)
            )
        ");
    }
}
