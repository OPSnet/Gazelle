<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateReportsv2 extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE reportsv2
            MODIFY ReportedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY LastChangeTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("UPDATE reportsv2 SET LastChangeTime = ReportedTime WHERE LastChangeTime = '0000-00-00 00:00:00'");
    }

    public function down() {
        $this->execute("ALTER TABLE reportsv2
            MODIFY ReportedTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            MODIFY LastChangeTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
        ");
    }
}

