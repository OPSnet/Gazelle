<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropGroupLog extends AbstractMigration {
    public function up(): void {
        $this->table('group_log')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE group_log (
                ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                GroupID int NOT NULL,
                TorrentID int NOT NULL,
                UserID int DEFAULT NULL,
                Info longtext,
                Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                Hidden tinyint NOT NULL DEFAULT '0',
                created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY GroupID (GroupID),
                KEY TorrentID (TorrentID)
            )
        ");
    }
}
