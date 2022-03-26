<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateReports extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE reports
            MODIFY ReportedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY ResolvedTime datetime DEFAULT NULL
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE reports
            MODIFY ReportedTime datetime
            MODIFY ResolvedTime datetime
        ");
    }
}

