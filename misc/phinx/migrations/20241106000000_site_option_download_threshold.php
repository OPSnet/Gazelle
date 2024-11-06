<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SiteOptionDownloadThreshold extends AbstractMigration {
    public function up(): void {
        $this->execute("
            INSERT INTO site_options
                   (Name, Value, Comment)
            VALUES (
                'download-warning-threshold',
                1500,
                'Show warning when downloads over three hours exceed this threshold')
        ");
    }

    public function down(): void {
        $this->execute("
            DELETE FROM site_options WHERE Name = 'download-warning-threshold'
        ");
    }
}
