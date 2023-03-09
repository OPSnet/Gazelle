<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DbCoherency extends AbstractMigration {
    public function up(): void {
        $this->query('ALTER TABLE deleted_torrents_bad_files MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_bad_folders MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_bad_tags MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_cassette_approved MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_group MODIFY WikiBody mediumtext');
        $this->query('ALTER TABLE deleted_torrents_lossymaster_approved MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_lossyweb_approved MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_torrents_missing_lineage MODIFY TimeAdded datetime');
        $this->query('ALTER TABLE deleted_users_notify_torrents MODIFY UnRead tinyint NOT NULL');
    }

    public function down(): void {
        $this->query('ALTER TABLE deleted_torrents_bad_files MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_bad_folders MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_bad_tags MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_cassette_approved MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_group MODIFY WikiBody longtext');
        $this->query('ALTER TABLE deleted_torrents_lossymaster_approved MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_lossyweb_approved MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_torrents_missing_lineage MODIFY TimeAdded timestamp DEFAULT NULL');
        $this->query('ALTER TABLE deleted_users_notify_torrents MODIFY UnRead int NOT NULL');
    }
}
