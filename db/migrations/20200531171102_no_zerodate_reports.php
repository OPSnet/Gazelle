<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateReports extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE reports
            MODIFY ReportedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY ResolvedTime datetime DEFAULT NULL
        ");
        $this->execute("UPDATE reports SET ResolvedTime = NULL WHERE ResolvedTime = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE reports
            MODIFY ReportedTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
            MODIFY ResolvedTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

