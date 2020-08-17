<?php

use Phinx\Migration\AbstractMigration;

class DropHtmlLogColumn extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE torrents_logs DROP COLUMN `Log`");
    }
    public function down() {
        $this->execute("ALTER TABLE torrents_logs ADD COLUMN `Log` mediumtext NOT NULL");
    }
}
