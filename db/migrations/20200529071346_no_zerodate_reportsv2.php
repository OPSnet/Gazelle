<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateReportsv2 extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE reportsv2
            MODIFY ReportedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY LastChangeTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE reportsv2
            MODIFY ReportedTime datetime,
            MODIFY LastChangeTime datetim
        ");
    }
}

