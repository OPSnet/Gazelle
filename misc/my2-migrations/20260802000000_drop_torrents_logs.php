<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropTorrentsLogs extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_logs')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE torrents_logs (
                LogID int NOT NULL AUTO_INCREMENT,
                TorrentID int NOT NULL DEFAULT '0',
                FileName varchar(255) NOT NULL DEFAULT '',
                Details longtext NOT NULL,
                Score int NOT NULL,
                Checksum enum('0', '1') NOT NULL DEFAULT '1',
                Adjusted enum('0', '1') NOT NULL DEFAULT '0',
                AdjustedScore int DEFAULT NULL,
                AdjustedChecksum enum('0', '1') NOT NULL DEFAULT '0',
                AdjustedBy int NOT NULL DEFAULT '0',
                AdjustmentReason mediumtext,
                AdjustmentDetails mediumtext,
                Ripper varchar(255) DEFAULT NULL,
                RipperVersion varchar(255) DEFAULT NULL,
                Language varchar(2) NOT NULL DEFAULT 'en',
                ChecksumState enum('checksum_ok', 'checksum_missing', 'checksum_invalid') NOT NULL DEFAULT 'checksum_ok',
                LogcheckerVersion varchar(255) NOT NULL DEFAULT '',
                PRIMARY KEY (LogID),
                KEY TorrentID (TorrentID)
            )
        ");
    }
}
