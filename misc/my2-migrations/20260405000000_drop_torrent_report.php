<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropTorrentReport extends AbstractMigration {
    public function up(): void {
        $this->table('torrent_report_configuration_log')->drop()->save();
        $this->table('torrent_report_configuration')->drop()->save();
    }

    public function down(): void {
        $this->query("
            CREATE TABLE torrent_report_configuration (
              torrent_report_configuration_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
              type varchar(20) NOT NULL,
              name varchar(30) NOT NULL,
              category_id int NOT NULL,
              sequence int NOT NULL,
              tracker_reason int NOT NULL DEFAULT '-1',
              is_active tinyint(1) NOT NULL DEFAULT '1',
              is_admin tinyint(1) NOT NULL DEFAULT '0',
              need_image enum('none','optional','required','proof') NOT NULL DEFAULT 'none',
              need_link enum('none','optional','required') NOT NULL DEFAULT 'none',
              need_sitelink enum('none','optional','required') NOT NULL DEFAULT 'none',
              need_track enum('none','optional','required','all') NOT NULL DEFAULT 'none',
              resolve_delete tinyint(1) NOT NULL DEFAULT '0',
              resolve_upload tinyint(1) NOT NULL DEFAULT '0',
              resolve_warn int NOT NULL DEFAULT '0',
              resolve_log varchar(80) DEFAULT NULL,
              explanation mediumtext NOT NULL,
              pm_body mediumtext,
              is_invisible tinyint(1) NOT NULL DEFAULT '0',
              UNIQUE KEY trc_type_uidx (type),
              KEY category_id (category_id),
              FOREIGN KEY (category_id) REFERENCES category (category_id)
            )
        ");

        $this->query("
            CREATE TABLE torrent_report_configuration_log (
              torrent_report_configuration_log_id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
              torrent_report_configuration_id int NOT NULL,
              created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              user_id int NOT NULL,
              change_set json NOT NULL,
              KEY torrent_report_configuration_id (torrent_report_configuration_id),
              KEY user_id (user_id),
              FOREIGN KEY (torrent_report_configuration_id) REFERENCES torrent_report_configuration (torrent_report_configuration_id),
              FOREIGN KEY (user_id) REFERENCES users_main (ID)
            )
        ");
    }
}
